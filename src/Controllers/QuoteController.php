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
use Nova\Models\ProjectRepository;
use Nova\Models\QuoteRepository;
use Nova\Services\AuditService;
use Nova\Services\LineItemService;
use Nova\Services\Mailer;
use Nova\Services\NumberSequenceService;
use Nova\Services\PdfService;

final class QuoteController extends Controller
{
    private QuoteRepository $repo;

    public function __construct()
    {
        $this->repo = new QuoteRepository();
    }

    public function index(Request $request): void
    {
        $this->view('quotes/list', [
            'title'  => 'Angebote',
            'quotes' => $this->repo->allWithCustomer(),
        ]);
    }

    public function create(Request $request): void
    {
        $settings = (new CompanySettingsRepository())->get();
        $this->view('quotes/form', [
            'title'     => 'Neues Angebot',
            'quote'     => $this->emptyQuote($settings),
            'items'     => [],
            'customers' => (new CustomerRepository())->forSelect(),
            'projects'  => (new ProjectRepository())->allWithCustomer(),
            'action'    => '/angebote',
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        if ($request->int('customer_id') === 0) {
            Session::flash('error', 'Bitte einen Kunden wählen.');
            $this->redirect('/angebote/neu');
        }

        [$header, $items] = $this->buildFromRequest($request);
        $id = $this->repo->createWithItems($header, $items);
        AuditService::record('create', 'quote', $id, null, ['items' => count($items)]);

        Session::flash('success', 'Angebot angelegt.');
        $this->redirect('/angebote/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $quote = $this->repo->findWithCustomer((int) $params['id']);
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }
        $this->view('quotes/show', [
            'title' => 'Angebot ' . ($quote['number'] ?: '(Entwurf)'),
            'quote' => $quote,
            'items' => $this->repo->items((int) $quote['id']),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $quote = $this->repo->findWithCustomer((int) $params['id']);
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }
        if ($quote['status'] !== 'draft') {
            Session::flash('error', 'Nur Angebote im Entwurf können bearbeitet werden.');
            $this->redirect('/angebote/' . $quote['id']);
        }
        $this->view('quotes/form', [
            'title'     => 'Angebot bearbeiten',
            'quote'     => $quote,
            'items'     => $this->repo->items((int) $quote['id']),
            'customers' => (new CustomerRepository())->forSelect((int) $quote['customer_id']),
            'projects'  => (new ProjectRepository())->allWithCustomer(),
            'action'    => '/angebote/' . $quote['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id    = (int) $params['id'];
        $quote = $this->repo->find($id);
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }
        if ($quote['status'] !== 'draft') {
            Session::flash('error', 'Nur Angebote im Entwurf können bearbeitet werden.');
            $this->redirect('/angebote/' . $id);
        }

        [$header, $items] = $this->buildFromRequest($request);
        $this->repo->updateWithItems($id, $header, $items);
        AuditService::record('update', 'quote', $id, null, ['items' => count($items)]);

        Session::flash('success', 'Angebot aktualisiert.');
        $this->redirect('/angebote/' . $id);
    }

    public function changeStatus(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id     = (int) $params['id'];
        $quote  = $this->repo->find($id);
        $status = $request->str('status');
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }
        if (!in_array($status, ['draft', 'sent', 'accepted', 'rejected'], true)) {
            Session::flash('error', 'Ungültiger Status.');
            $this->redirect('/angebote/' . $id);
        }

        // Beim ersten Versenden eine Angebotsnummer vergeben.
        if ($status === 'sent' && empty($quote['number'])) {
            $format = (new CompanySettingsRepository())->get()['quote_number_format'];
            $this->repo->setNumber($id, NumberSequenceService::next('quote', $format));
        }
        $this->repo->setStatus($id, $status);
        AuditService::record('update', 'quote', $id, ['status' => $quote['status']], ['status' => $status]);

        Session::flash('success', 'Status aktualisiert.');
        $this->redirect('/angebote/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id    = (int) $params['id'];
        $quote = $this->repo->find($id);
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }
        if ($quote['status'] !== 'draft') {
            Session::flash('error', 'Nur Angebote im Entwurf können gelöscht werden. Versendete Angebote stattdessen auf „Abgelehnt" setzen.');
            $this->redirect('/angebote/' . $id);
        }
        if (!empty($quote['converted_invoice_id'])) {
            Session::flash('error', 'Aus diesem Angebot wurde bereits eine Rechnung erzeugt – es kann nicht gelöscht werden.');
            $this->redirect('/angebote/' . $id);
        }

        $this->repo->delete($id);
        AuditService::record('delete', 'quote', $id, $quote, null);

        Session::flash('success', 'Angebot gelöscht.');
        $this->redirect('/angebote');
    }

    public function pdf(Request $request, array $params): void
    {
        $quote = $this->repo->findWithCustomer((int) $params['id']);
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }
        $settings = (new CompanySettingsRepository())->get();
        $name = 'Angebot-' . ($quote['number'] ?: 'Entwurf-' . $quote['id']) . '.pdf';
        PdfService::stream('pdf/quote', [
            'quote'    => $quote,
            'items'    => $this->repo->items((int) $quote['id']),
            'settings' => $settings,
        ], str_replace([' ', '/'], '-', $name));
    }

    public function send(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id    = (int) $params['id'];
        $quote = $this->repo->findWithCustomer($id);
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }

        $email = (string) DB::getInstance()->fetchColumn(
            'SELECT email FROM customer WHERE id = :id',
            ['id' => (int) $quote['customer_id']]
        );
        if (trim($email) === '') {
            Session::flash('error', 'Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.');
            $this->redirect('/angebote/' . $id);
        }

        $settings = (new CompanySettingsRepository())->get();

        // Beim Versand eine Angebotsnummer vergeben und Status auf „versendet".
        if (empty($quote['number'])) {
            $this->repo->setNumber($id, NumberSequenceService::next('quote', $settings['quote_number_format']));
        }
        if ($quote['status'] === 'draft') {
            $this->repo->setStatus($id, 'sent');
        }
        $quote = $this->repo->findWithCustomer($id); // mit frischer Nummer/Status

        $pdf    = PdfService::renderToString('pdf/quote', [
            'quote'    => $quote,
            'items'    => $this->repo->items($id),
            'settings' => $settings,
        ]);
        $anrede = $quote['contact_name'] ?: $quote['company_name'];

        $vars = [
            '{kunde}'   => (string) $anrede,
            '{nummer}'  => (string) $quote['number'],
            '{datum}'   => Format::date($quote['quote_date']),
            '{betrag}'  => Format::money((int) $quote['gross_total_cents']),
            '{faellig}' => $quote['valid_until'] ? Format::date($quote['valid_until']) : '',
            '{firma}'   => (string) ($settings['company_name'] ?? ''),
        ];
        $settings['email_signature'] = (string) ($settings['email_signature'] ?? '') ?: CompanySettingsRepository::DEFAULT_EMAIL_SIGNATURE;
        ['subject' => $subject, 'body' => $body] = Mailer::compose(
            $settings,
            (string) ($settings['quote_email_subject'] ?? '') ?: CompanySettingsRepository::DEFAULT_QUOTE_EMAIL_SUBJECT,
            CompanySettingsRepository::DEFAULT_QUOTE_EMAIL_SUBJECT,
            (string) ($settings['quote_email_body'] ?? '') ?: CompanySettingsRepository::DEFAULT_QUOTE_EMAIL_BODY,
            CompanySettingsRepository::DEFAULT_QUOTE_EMAIL_BODY,
            $vars
        );

        try {
            Mailer::send($settings, $email, (string) $anrede, $subject, $body, [
                ['name' => 'Angebot-' . str_replace(['/', ' '], '-', (string) $quote['number']) . '.pdf', 'data' => $pdf, 'mime' => 'application/pdf'],
            ]);
        } catch (\RuntimeException $e) {
            Session::flash('error', 'Versand fehlgeschlagen: ' . $e->getMessage());
            $this->redirect('/angebote/' . $id);
        }

        AuditService::record('email', 'quote', $id, null, ['to' => $email]);
        Session::flash('success', 'Angebot ' . $quote['number'] . ' an ' . $email . ' versendet.');
        $this->redirect('/angebote/' . $id);
    }

    public function convertToInvoice(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $id    = (int) $params['id'];
        $quote = $this->repo->findWithCustomer($id);
        if ($quote === null) {
            Response::notFound('Angebot nicht gefunden.');
            return;
        }
        if (!empty($quote['converted_invoice_id'])) {
            Session::flash('warn', 'Aus diesem Angebot wurde bereits eine Rechnung erzeugt.');
            $this->redirect('/rechnungen/' . $quote['converted_invoice_id']);
        }

        $invoiceId = (new InvoiceRepository())->createFromQuote($quote, $this->repo->items($id));
        $this->repo->setConvertedInvoice($id, $invoiceId);
        AuditService::record('create', 'invoice', $invoiceId, ['from_quote' => $id], null);

        Session::flash('success', 'Rechnungsentwurf aus Angebot erzeugt.');
        $this->redirect('/rechnungen/' . $invoiceId);
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}
     */
    private function buildFromRequest(Request $request): array
    {
        $settings = (new CompanySettingsRepository())->get();
        $isKU     = (int) $settings['is_kleinunternehmer'] === 1;
        $vatRate  = $isKU ? 0 : (int) $settings['default_vat_rate'];

        $items  = LineItemService::parse($request->post);
        $totals = LineItemService::totals($items, $vatRate, $isKU);

        $header = [
            'customer_id'         => $request->int('customer_id'),
            'project_id'          => $request->int('project_id') ?: null,
            'status'              => 'draft',
            'quote_date'          => $request->str('quote_date') ?: date('Y-m-d'),
            'valid_until'         => $request->str('valid_until') ?: null,
            'is_kleinunternehmer' => $isKU ? 1 : 0,
            'vat_rate'            => $vatRate,
            'intro_text'          => $request->str('intro_text'),
            'footer_text'         => $request->str('footer_text') ?: $settings['quote_footer_text'],
            'net_total_cents'     => $totals['net_total_cents'],
            'vat_total_cents'     => $totals['vat_total_cents'],
            'gross_total_cents'   => $totals['gross_total_cents'],
        ];
        return [$header, $items];
    }

    /** @param array<string,mixed> $settings @return array<string,mixed> */
    private function emptyQuote(array $settings): array
    {
        return [
            'id' => null, 'number' => null, 'customer_id' => 0, 'project_id' => null,
            'status' => 'draft', 'quote_date' => date('Y-m-d'), 'valid_until' => '',
            'is_kleinunternehmer' => (int) $settings['is_kleinunternehmer'],
            'vat_rate' => (int) $settings['default_vat_rate'],
            'intro_text' => '', 'footer_text' => $settings['quote_footer_text'],
        ];
    }
}
