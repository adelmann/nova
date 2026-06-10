<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Core\View;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\InvoiceRepository;
use Nova\Models\ReminderRepository;
use Nova\Services\AuditService;
use Nova\Services\Mailer;
use Nova\Services\PdfService;

final class ReminderController extends Controller
{
    private ReminderRepository $repo;

    public function __construct()
    {
        $this->repo = new ReminderRepository();
    }

    /** Bezeichnungen der Mahnstufen. */
    public static function levelLabel(int $level): string
    {
        return match ($level) {
            1       => 'Zahlungserinnerung',
            2       => '1. Mahnung',
            3       => '2. Mahnung',
            default => $level - 1 . '. Mahnung',
        };
    }

    public function index(Request $request): void
    {
        // Überfällige, finalisierte, nicht stornierte/bezahlte Rechnungen.
        $overdue = DB::getInstance()->fetchAll(
            "SELECT i.id, i.number, i.due_date, i.gross_total_cents - i.paid_total_cents AS offen,
                    c.company_name, c.contact_name,
                    (SELECT COALESCE(MAX(level),0) FROM reminder r WHERE r.invoice_id = i.id) AS last_level
             FROM invoice i JOIN customer c ON c.id = i.customer_id
             WHERE i.is_locked = 1 AND i.status IN ('sent','overdue')
               AND i.due_date IS NOT NULL AND i.due_date < :today
             ORDER BY i.due_date",
            ['today' => date('Y-m-d')]
        );

        $this->view('reminders/index', [
            'title'     => 'Mahnwesen',
            'overdue'   => $overdue,
            'reminders' => $this->repo->allWithInvoice(),
        ]);
    }

    public function store(Request $request): void
    {
        $this->verifyCsrf($request);

        $invoiceId = $request->int('invoice_id');
        $invoiceRepo = new InvoiceRepository();
        $invoice = $invoiceRepo->findWithCustomer($invoiceId);
        if ($invoice === null) {
            Response::notFound('Rechnung nicht gefunden.');
            return;
        }

        $level    = $this->repo->highestLevel($invoiceId) + 1;
        $feeCents  = Format::toCents($request->str('fee'));
        $settings  = (new CompanySettingsRepository())->get();
        $offen     = (int) $invoice['gross_total_cents'] - (int) $invoice['paid_total_cents'];

        $emailText = $this->buildEmailText($invoice, $level, $offen, $feeCents, $settings);

        $id = $this->repo->createReminder([
            'invoice_id'    => $invoiceId,
            'level'         => $level,
            'reminder_date' => date('Y-m-d'),
            'fee_cents'     => $feeCents,
            'email_text'    => $emailText,
        ]);

        // PDF erzeugen und archivieren.
        $relPath = date('Y') . '/Mahnung-' . str_replace(['/', ' '], '-', (string) $invoice['number']) . '-Stufe' . $level . '.pdf';
        $absPath = ($GLOBALS['nova_config']['paths']['invoices'] ?? '') . '/' . $relPath;
        PdfService::renderToFile('pdf/reminder', [
            'invoice'  => $invoice,
            'level'    => $level,
            'offen'    => $offen,
            'feeCents' => $feeCents,
            'settings' => $settings,
        ], $absPath);
        $this->repo->setPdfPath($id, $relPath);

        AuditService::record('create', 'reminder', $id, null, ['invoice_id' => $invoiceId, 'level' => $level]);
        Session::flash('success', self::levelLabel($level) . ' für Rechnung ' . $invoice['number'] . ' erstellt.');
        $this->redirect('/mahnungen');
    }

    public function pdf(Request $request, array $params): void
    {
        $reminder = $this->repo->find((int) $params['id']);
        if ($reminder === null) {
            Response::notFound('Mahnung nicht gefunden.');
            return;
        }
        $abs = ($GLOBALS['nova_config']['paths']['invoices'] ?? '') . '/' . $reminder['pdf_path'];
        if (is_file($abs)) {
            Response::inline($abs, 'application/pdf');
        }
        Response::notFound('Mahnungs-PDF fehlt.');
    }

    public function send(Request $request, array $params): void
    {
        $this->verifyCsrf($request);

        $reminder = $this->repo->find((int) $params['id']);
        if ($reminder === null) {
            Response::notFound('Mahnung nicht gefunden.');
            return;
        }

        $invoice = (new InvoiceRepository())->findWithCustomer((int) $reminder['invoice_id']);
        if ($invoice === null) {
            Session::flash('error', 'Zugehörige Rechnung nicht gefunden.');
            $this->redirect('/mahnungen');
        }

        $email = (string) DB::getInstance()->fetchColumn(
            'SELECT email FROM customer WHERE id = :id',
            ['id' => (int) $invoice['customer_id']]
        );
        if (trim($email) === '') {
            Session::flash('error', 'Für diesen Kunden ist keine E-Mail-Adresse hinterlegt.');
            $this->redirect('/mahnungen');
        }

        $settings = (new CompanySettingsRepository())->get();
        $label    = self::levelLabel((int) $reminder['level']);
        $text     = (string) $reminder['email_text'];

        // Betreffzeile aus dem vorbereiteten Text lösen (erste „Betreff:"-Zeile).
        $subject = "{$label} zu Rechnung {$invoice['number']}";
        $body    = $text;
        if (preg_match('/^Betreff:\s*(.+)$/m', $text, $m) === 1) {
            $subject = trim($m[1]);
            $body    = trim((string) preg_replace('/^Betreff:.*$/m', '', $text, 1));
        }

        $attachments = [];
        $abs = ($GLOBALS['nova_config']['paths']['invoices'] ?? '') . '/' . $reminder['pdf_path'];
        if (is_file($abs)) {
            $attachments[] = [
                'name' => 'Mahnung-' . str_replace(['/', ' '], '-', (string) $invoice['number']) . '-Stufe' . $reminder['level'] . '.pdf',
                'data' => (string) file_get_contents($abs),
                'mime' => 'application/pdf',
            ];
        }

        $anrede = $invoice['contact_name'] ?: $invoice['company_name'];
        try {
            Mailer::send($settings, $email, (string) $anrede, $subject, $body, $attachments);
        } catch (\RuntimeException $e) {
            Session::flash('error', 'Versand fehlgeschlagen: ' . $e->getMessage());
            $this->redirect('/mahnungen');
        }

        AuditService::record('email', 'reminder', (int) $reminder['id'], null, ['to' => $email]);
        Session::flash('success', $label . ' an ' . $email . ' versendet.');
        $this->redirect('/mahnungen');
    }

    /** @param array<string,mixed> $invoice @param array<string,mixed> $settings */
    private function buildEmailText(array $invoice, int $level, int $offen, int $feeCents, array $settings): string
    {
        $anrede = $invoice['contact_name'] ?: $invoice['company_name'];
        $label  = self::levelLabel($level);
        $betrag = Format::money($offen + $feeCents);
        $lines  = [
            "Betreff: {$label} zu Rechnung {$invoice['number']}",
            '',
            "Sehr geehrte Damen und Herren, {$anrede},",
            '',
            $level === 1
                ? "unsere Rechnung {$invoice['number']} vom " . Format::date($invoice['invoice_date']) . " ist seit dem " . Format::date($invoice['due_date']) . " fällig. Möglicherweise ist Ihnen die Zahlung entgangen."
                : "trotz unserer bisherigen Erinnerung ist die Rechnung {$invoice['number']} weiterhin offen.",
            '',
            "Bitte überweisen Sie den offenen Betrag von " . Format::money($offen)
                . ($feeCents > 0 ? " zzgl. Mahngebühr von " . Format::money($feeCents) . " (gesamt {$betrag})" : '')
                . " bis zum " . date('d.m.Y', strtotime('+7 days')) . " auf das Konto {$settings['iban']}.",
            '',
            'Sollte sich Ihre Zahlung mit diesem Schreiben überschnitten haben, betrachten Sie es bitte als gegenstandslos.',
            '',
            'Mit freundlichen Grüßen',
            $settings['owner_name'] ?: $settings['company_name'],
        ];
        return implode("\n", $lines);
    }
}
