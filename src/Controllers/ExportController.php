<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Response;
use Nova\Core\Session;
use Nova\Services\EuerService;

final class ExportController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('exports/index', [
            'title' => 'Exporte',
            'years' => EuerService::years(),
            'year'  => $request->int('jahr') ?: (int) date('Y'),
        ]);
    }

    public function incomeCsv(Request $request): void
    {
        $year = $request->int('jahr') ?: (int) date('Y');
        $rows = DB::getInstance()->fetchAll(
            "SELECT p.paid_on, i.number, c.company_name, c.contact_name, p.method, p.amount_cents
             FROM payment p JOIN invoice i ON i.id = p.invoice_id JOIN customer c ON c.id = i.customer_id
             WHERE strftime('%Y', p.paid_on) = :y ORDER BY p.paid_on",
            ['y' => (string) $year]
        );
        $out = [['Datum', 'Rechnung', 'Kunde', 'Zahlart', 'Betrag']];
        foreach ($rows as $r) {
            $out[] = [Format::date($r['paid_on']), $r['number'], $r['company_name'] ?: $r['contact_name'], $r['method'], Format::amount((int) $r['amount_cents'])];
        }
        Response::csv("Einnahmen-{$year}.csv", $this->csv($out));
    }

    public function expensesCsv(Request $request): void
    {
        $year = $request->int('jahr') ?: (int) date('Y');
        $rows = DB::getInstance()->fetchAll(
            "SELECT expense_date, supplier, tax_category, category, method, status, amount_cents
             FROM expense WHERE strftime('%Y', expense_date) = :y ORDER BY expense_date",
            ['y' => (string) $year]
        );
        $out = [['Datum', 'Lieferant', 'EÜR-Kategorie', 'Kategorie', 'Zahlart', 'Status', 'Betrag']];
        foreach ($rows as $r) {
            $out[] = [Format::date($r['expense_date']), $r['supplier'], $r['tax_category'], $r['category'], $r['method'], $r['status'], Format::amount((int) $r['amount_cents'])];
        }
        Response::csv("Ausgaben-{$year}.csv", $this->csv($out));
    }

    public function journalCsv(Request $request): void
    {
        $year = $request->int('jahr') ?: (int) date('Y');
        $rows = DB::getInstance()->fetchAll(
            "SELECT entry_date, type, category, description, amount_cents, reference_type, reference_id
             FROM ledger_entry WHERE strftime('%Y', entry_date) = :y ORDER BY entry_date, id",
            ['y' => (string) $year]
        );
        $out = [['Datum', 'Typ', 'Kategorie', 'Beschreibung', 'Betrag', 'Referenz']];
        foreach ($rows as $r) {
            $out[] = [Format::date($r['entry_date']), $r['type'] === 'income' ? 'Einnahme' : 'Ausgabe', $r['category'], $r['description'], Format::amount((int) $r['amount_cents']), $r['reference_type'] . ' ' . $r['reference_id']];
        }
        Response::csv("Buchungsjournal-{$year}.csv", $this->csv($out));
    }

    /** ZIP aller archivierten Rechnungen + Belege eines Jahres. */
    public function yearZip(Request $request): void
    {
        $year = $request->int('jahr') ?: (int) date('Y');
        $this->streamZip("Nova-Jahresexport-{$year}.zip", $year, includeInvoices: true, includeReceipts: true);
    }

    /** ZIP nur der Belege eines Jahres. */
    public function receiptsZip(Request $request): void
    {
        $year = $request->int('jahr') ?: (int) date('Y');
        $this->streamZip("Belege-{$year}.zip", $year, includeInvoices: false, includeReceipts: true);
    }

    private function streamZip(string $filename, int $year, bool $includeInvoices, bool $includeReceipts): void
    {
        if (!class_exists(\ZipArchive::class)) {
            Session::flash('error', 'ZIP-Export ist auf diesem Server nicht verfügbar (ext-zip fehlt).');
            $this->redirect('/exporte');
        }

        $cfg     = $GLOBALS['nova_config']['paths'];
        $tmpFile = tempnam(sys_get_temp_dir(), 'nova_zip_');
        $zip     = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::OVERWRITE);

        if ($includeInvoices) {
            $rows = DB::getInstance()->fetchAll(
                "SELECT number, pdf_archive_path FROM invoice
                 WHERE pdf_archive_path <> '' AND strftime('%Y', invoice_date) = :y",
                ['y' => (string) $year]
            );
            foreach ($rows as $r) {
                $abs = $cfg['invoices'] . '/' . $r['pdf_archive_path'];
                if (is_file($abs)) {
                    $zip->addFile($abs, 'Rechnungen/' . basename($r['pdf_archive_path']));
                }
            }
        }

        if ($includeReceipts) {
            $rows = DB::getInstance()->fetchAll(
                "SELECT stored_path, original_name FROM receipt WHERE strftime('%Y', created_at) = :y",
                ['y' => (string) $year]
            );
            foreach ($rows as $r) {
                $abs = $cfg['receipts'] . '/' . $r['stored_path'];
                if (is_file($abs)) {
                    $zip->addFile($abs, 'Belege/' . basename($r['stored_path']) . '_' . $r['original_name']);
                }
            }
        }

        // EÜR-CSV als Beilage.
        $zip->addFromString("EUER-{$year}.txt", $this->euerText($year));
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($tmpFile));
        readfile($tmpFile);
        @unlink($tmpFile);
        exit;
    }

    private function euerText(int $year): string
    {
        $s = EuerService::summary($year);
        return "EÜR {$year}\n"
            . 'Einnahmen: ' . Format::money($s['income']) . "\n"
            . 'Ausgaben:  ' . Format::money($s['expense']) . "\n"
            . 'Gewinn:    ' . Format::money($s['profit']) . "\n";
    }

    /** @param array<int,array<int,string>> $rows */
    private function csv(array $rows): string
    {
        $out = '';
        foreach ($rows as $row) {
            $out .= implode(';', array_map(static fn (string $v): string => '"' . str_replace('"', '""', $v) . '"', $row)) . "\r\n";
        }
        return $out;
    }
}
