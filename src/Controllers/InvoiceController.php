<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\CustomerRepository;
use Nova\Models\InvoiceRepository;
use Nova\Models\PaymentRepository;
use Nova\Models\ProjectRepository;
use Nova\Models\ReceiptRepository;
use Nova\Services\AuditService;
use Nova\Services\LedgerService;
use Nova\Services\LineItemService;
use Nova\Services\Mailer;
use Nova\Services\PdfService;

final class InvoiceController extends Controller
{
    private InvoiceRepository $repo;

    public function __construct()
    {
        $this->repo = new InvoiceRepository();
    }

    public function index(Request $request): void
    {
        $this->view('invoices/list', [
            'title'    => 'Rechnungen',
            'invoices' => $this->repo->allWithCustomer(),
        ]);
    }

    public function create(Request $request): void
    {
        $settings = (new CompanySettingsRepository())->get();
        $this->view('invoices/form', [
            'title'     => 'Neue Rechnung',
            'invoice'   => $this->emptyInvoice($settings),
            'items'     => [],
            'customers' => (new CustomerRepository())->forSelect(),
            'projects'  => (new ProjectRepository())->allWithCustomer(),
            'action'    => '/rechnungen',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        if ($request->int('customer_id') === 0) {
            Session::flash('error', 'Bitte einen Kunden wählen.');
            $this->redirect('/rechnungen/neu');
        }

        [$header, $items] = $this->buildFromRequest($request);
        $id = $this->repo->createWithItems($header, $items);
        AuditService::record('create', 'invoice', $id, null, ['items' => count($items)]);

        Session::flash('success', 'Rechnungsentwurf angelegt.');
        $this->redirect('/rechnungen/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $invoice = $this->repo->findWithCustomer((int) $params['id']);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        $original = null;
        if (!empty($invoice['cancels_invoice_id'])) {
            $original = $this->repo->find((int) $invoice['cancels_invoice_id']);
        }
        $this->view('invoices/show', [
            'title'    => 'Rechnung ' . ($invoice['number'] ?: '(Entwurf)'),
            'invoice'  => $invoice,
            'items'    => $this->repo->items((int) $invoice['id']),
            'payments' => $this->repo->payments((int) $invoice['id']),
            'receipts' => (new ReceiptRepository())->forLinkable('invoice', (int) $invoice['id']),
            'original' => $original,
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $invoice = $this->repo->findWithCustomer((int) $params['id']);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] === 1) {
            Session::flash('error', 'Finalisierte Rechnungen können nicht bearbeitet werden.');
            $this->redirect('/rechnungen/' . $invoice['id']);
        }
        $this->view('invoices/form', [
            'title'     => 'Rechnung bearbeiten',
            'invoice'   => $invoice,
            'items'     => $this->repo->items((int) $invoice['id']),
            'customers' => (new CustomerRepository())->forSelect((int) $invoice['customer_id']),
            'projects'  => (new ProjectRepository())->allWithCustomer(),
            'action'    => '/rechnungen/' . $invoice['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $invoice = $this->repo->find($id);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] === 1) {
            Session::flash('error', 'Finalisierte Rechnungen können nicht bearbeitet werden.');
            $this->redirect('/rechnungen/' . $id);
        }

        [$header, $items] = $this->buildFromRequest($request);
        $this->repo->updateWithItems($id, $header, $items);
        AuditService::record('update', 'invoice', $id, null, ['items' => count($items)]);

        Session::flash('success', 'Rechnung aktualisiert.');
        $this->redirect('/rechnungen/' . $id);
    }

    public function finalize(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $invoice = $this->repo->find($id);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] === 1) {
            Session::flash('warn', 'Rechnung ist bereits finalisiert.');
            $this->redirect('/rechnungen/' . $id);
        }
        if ($this->repo->items($id) === []) {
            Session::flash('error', 'Eine Rechnung ohne Positionen kann nicht finalisiert werden.');
            $this->redirect('/rechnungen/' . $id);
        }

        $settings = (new CompanySettingsRepository())->get();
        $number   = $this->repo->finalize($id, $settings['invoice_number_format'], (int) $settings['default_payment_days']);

        // Finales PDF einfrieren (Archiv).
        $this->archivePdf($id, $number, $settings);

        AuditService::record('finalize', 'invoice', $id, ['status' => $invoice['status']], ['number' => $number, 'is_locked' => 1]);
        Session::flash('success', 'Rechnung ' . $number . ' finalisiert und archiviert.');
        $this->redirect('/rechnungen/' . $id);
    }

    public function cancel(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $invoice = $this->repo->find($id);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] !== 1) {
            Session::flash('error', 'Nur finalisierte Rechnungen können storniert werden.');
            $this->redirect('/rechnungen/' . $id);
        }
        if ($invoice['status'] === 'cancelled') {
            Session::flash('warn', 'Rechnung ist bereits storniert.');
            $this->redirect('/rechnungen/' . $id);
        }

        $settings = (new CompanySettingsRepository())->get();
        $cancelId = $this->repo->createCancellation($id, $settings['invoice_number_format']);

        // Falls bereits Zahlungen verbucht waren: Gegenbuchung im Journal.
        $paid = (int) $invoice['paid_total_cents'];
        if ($paid > 0) {
            LedgerService::recordIncome(
                date('Y-m-d'),
                -$paid,
                'invoice_cancellation',
                $cancelId,
                'Storno',
                'Storno zu Rechnung ' . $invoice['number']
            );
        }

        $this->archivePdf($cancelId, (string) $this->repo->find($cancelId)['number'], $settings);
        AuditService::record('cancel', 'invoice', $id, ['status' => $invoice['status']], ['cancelled_by_invoice' => $cancelId]);

        Session::flash('success', 'Storno-Rechnung erstellt.');
        $this->redirect('/rechnungen/' . $cancelId);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $invoice = $this->repo->find($id);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] === 1) {
            Session::flash('error', 'Finalisierte Rechnungen können nicht gelöscht werden (GoBD). Bitte stornieren.');
            $this->redirect('/rechnungen/' . $id);
        }
        $this->repo->delete($id);
        AuditService::record('delete', 'invoice', $id, $invoice, null);

        Session::flash('success', 'Rechnungsentwurf gelöscht.');
        $this->redirect('/rechnungen');
    }

    public function pdf(Request $request, array $params): void
    {
        $invoice = $this->repo->findWithCustomer((int) $params['id']);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }

        // Finalisierte Rechnung: archiviertes PDF unverändert ausliefern.
        if (!empty($invoice['pdf_archive_path'])) {
            $abs = ($GLOBALS['nova_config']['paths']['invoices'] ?? '') . '/' . $invoice['pdf_archive_path'];
            if (is_file($abs)) {
                Response::inline($abs, 'application/pdf');
            }
        }

        $settings = (new CompanySettingsRepository())->get();
        $name = 'Rechnung-' . ($invoice['number'] ?: 'Entwurf-' . $invoice['id']) . '.pdf';
        PdfService::stream('pdf/invoice', [
            'invoice'  => $invoice,
            'items'    => $this->repo->items((int) $invoice['id']),
            'settings' => $settings,
        ], str_replace([' ', '/'], '-', $name));
    }

    public function xrechnung(Request $request, array $params): void
    {
        $invoice = $this->repo->findWithCustomer((int) $params['id']);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] !== 1) {
            Session::flash('error', 'E-Rechnungen können nur für finalisierte Rechnungen erzeugt werden.');
            $this->redirect('/rechnungen/' . $invoice['id']);
        }

        $settings = (new CompanySettingsRepository())->get();
        $xml = \Nova\Services\XRechnungService::generate(
            $invoice,
            $this->repo->items((int) $invoice['id']),
            $settings
        );

        $name = 'XRechnung-' . str_replace(['/', ' '], '-', (string) $invoice['number']) . '.xml';
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        echo $xml;
        exit;
    }

    public function addPayment(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $invoice = $this->repo->find($id);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] !== 1 || $invoice['status'] === 'cancelled') {
            Session::flash('error', 'Zahlungen können nur zu finalisierten, nicht stornierten Rechnungen erfasst werden.');
            $this->redirect('/rechnungen/' . $id);
        }

        $amount = Format::toCents($request->str('amount'));
        if ($amount <= 0) {
            Session::flash('error', 'Bitte einen gültigen Zahlbetrag angeben.');
            $this->redirect('/rechnungen/' . $id);
        }

        $paidOn = $request->str('paid_on') ?: date('Y-m-d');
        $method = pick_value($request->str('method', 'Überweisung'), $request->str('method_custom')) ?: 'Überweisung';

        $paymentId = (new PaymentRepository())->createPayment($id, $paidOn, $amount, $method, $request->str('note'));

        // Automatischer Journal-Eintrag (Einnahme).
        LedgerService::recordIncome(
            $paidOn,
            $amount,
            'payment',
            $paymentId,
            'Umsatzerlöse',
            'Zahlungseingang Rechnung ' . $invoice['number']
        );

        $this->repo->recalcPaymentStatus($id);
        AuditService::record('payment', 'invoice', $id, null, ['amount_cents' => $amount, 'paid_on' => $paidOn]);

        Session::flash('success', 'Zahlung erfasst.');
        $this->redirect('/rechnungen/' . $id);
    }

    public function send(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id      = (int) $params['id'];
        $invoice = $this->repo->findWithCustomer($id);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }
        if ((int) $invoice['is_locked'] !== 1) {
            Session::flash('error', 'Bitte die Rechnung zuerst finalisieren, dann versenden.');
            $this->redirect('/rechnungen/' . $id);
        }

        $email = (string) DB::getInstance()->fetchColumn(
            'SELECT email FROM customer WHERE id = :id',
            ['id' => (int) $invoice['customer_id']]
        );
        if (trim($email) === '') {
            Session::flash('error', 'Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.');
            $this->redirect('/rechnungen/' . $id);
        }

        $settings = (new CompanySettingsRepository())->get();
        $pdf      = $this->pdfBytes($invoice, $settings);
        $anrede   = $invoice['contact_name'] ?: $invoice['company_name'];

        $vars = [
            '{kunde}'   => (string) $anrede,
            '{nummer}'  => (string) $invoice['number'],
            '{datum}'   => Format::date($invoice['invoice_date']),
            '{betrag}'  => Format::money((int) $invoice['gross_total_cents']),
            '{faellig}' => $invoice['due_date'] ? Format::date($invoice['due_date']) : '',
            '{firma}'   => (string) ($settings['company_name'] ?? ''),
        ];
        $settings['email_signature'] = (string) ($settings['email_signature'] ?? '') ?: CompanySettingsRepository::DEFAULT_EMAIL_SIGNATURE;
        ['subject' => $subject, 'body' => $body] = Mailer::compose(
            $settings,
            (string) ($settings['invoice_email_subject'] ?? '') ?: CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_SUBJECT,
            CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_SUBJECT,
            (string) ($settings['invoice_email_body'] ?? '') ?: CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_BODY,
            CompanySettingsRepository::DEFAULT_INVOICE_EMAIL_BODY,
            $vars
        );

        try {
            Mailer::send($settings, $email, (string) $anrede, $subject, $body, [
                ['name' => 'Rechnung-' . str_replace(['/', ' '], '-', (string) $invoice['number']) . '.pdf', 'data' => $pdf, 'mime' => 'application/pdf'],
            ]);
        } catch (\RuntimeException $e) {
            Session::flash('error', 'Versand fehlgeschlagen: ' . $e->getMessage());
            $this->redirect('/rechnungen/' . $id);
        }

        AuditService::record('email', 'invoice', $id, null, ['to' => $email]);
        Session::flash('success', 'Rechnung ' . $invoice['number'] . ' an ' . $email . ' versendet.');
        $this->redirect('/rechnungen/' . $id);
    }

    /**
     * Liefert die PDF-Bytes: bei finalisierten Rechnungen das archivierte PDF,
     * sonst frisch gerendert.
     *
     * @param array<string,mixed> $invoice @param array<string,mixed> $settings
     */
    private function pdfBytes(array $invoice, array $settings): string
    {
        if (!empty($invoice['pdf_archive_path'])) {
            $abs = ($GLOBALS['nova_config']['paths']['invoices'] ?? '') . '/' . $invoice['pdf_archive_path'];
            if (is_file($abs)) {
                return (string) file_get_contents($abs);
            }
        }
        return PdfService::renderToString('pdf/invoice', [
            'invoice'  => $invoice,
            'items'    => $this->repo->items((int) $invoice['id']),
            'settings' => $settings,
        ]);
    }

    /** @param array<string,mixed> $settings */
    private function archivePdf(int $id, string $number, array $settings): void
    {
        $invoice = $this->repo->findWithCustomer($id);
        if ($invoice === null) {
            return;
        }
        $relPath = date('Y') . '/Rechnung-' . str_replace(['/', ' '], '-', $number) . '.pdf';
        $absPath = ($GLOBALS['nova_config']['paths']['invoices'] ?? '') . '/' . $relPath;
        PdfService::renderToFile('pdf/invoice', [
            'invoice'  => $invoice,
            'items'    => $this->repo->items($id),
            'settings' => $settings,
        ], $absPath);
        $this->repo->setArchivePath($id, $relPath);
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}
     */
    private function buildFromRequest(Request $request): array
    {
        $settings = (new CompanySettingsRepository())->get();
        $isKU     = (int) $settings['is_kleinunternehmer'] === 1;
        $vatRate  = $isKU ? 0 : (int) $settings['default_vat_rate'];

        $items = LineItemService::parse($request->post);
        foreach ($items as &$it) {
            $it['vat_rate'] = $vatRate;
        }
        unset($it);
        $totals = LineItemService::totals($items, $vatRate, $isKU);

        $header = [
            'customer_id'         => $request->int('customer_id'),
            'project_id'          => $request->int('project_id') ?: null,
            'status'              => 'draft',
            'invoice_date'        => $request->str('invoice_date') ?: date('Y-m-d'),
            'service_date_from'   => $request->str('service_date_from') ?: null,
            'service_date_to'     => $request->str('service_date_to') ?: null,
            'is_kleinunternehmer' => $isKU ? 1 : 0,
            'vat_rate'            => $vatRate,
            'intro_text'          => $request->str('intro_text'),
            'footer_text'         => $request->str('footer_text') ?: $settings['invoice_footer_text'],
            'net_total_cents'     => $totals['net_total_cents'],
            'vat_total_cents'     => $totals['vat_total_cents'],
            'gross_total_cents'   => $totals['gross_total_cents'],
        ];
        return [$header, $items];
    }

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    private function emptyInvoice(array $settings): array
    {
        return [
            'id' => null, 'number' => null, 'customer_id' => 0, 'project_id' => null,
            'status' => 'draft', 'is_locked' => 0, 'invoice_date' => date('Y-m-d'),
            'service_date_from' => '', 'service_date_to' => '',
            'is_kleinunternehmer' => (int) $settings['is_kleinunternehmer'],
            'vat_rate' => (int) $settings['default_vat_rate'],
            'intro_text' => '', 'footer_text' => $settings['invoice_footer_text'],
            'net_total_cents' => 0, 'vat_total_cents' => 0, 'gross_total_cents' => 0, 'paid_total_cents' => 0,
        ];
    }
}
