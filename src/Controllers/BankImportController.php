<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Format;
use Nova\Core\Request;
use Nova\Core\Session;
use Nova\Models\ExpenseRepository;
use Nova\Models\InvoiceRepository;
use Nova\Models\PaymentRepository;
use Nova\Services\AuditService;
use Nova\Services\LedgerService;

/**
 * Importiert Bankumsätze aus einer CSV-Datei. Negative Beträge können als
 * Ausgaben übernommen werden. Ablauf: Upload → Vorschau → Buchen (stateless,
 * die geparsten Zeilen werden als Formularfelder durchgereicht).
 */
final class BankImportController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('bankimport/upload', ['title' => 'Bankimport']);
    }

    public function preview(Request $request): void
    {
        $this->verifyCsrf($request);

        $file = $request->file('csv');
        if ($file === null) {
            Session::flash('error', 'Bitte eine CSV-Datei auswählen.');
            $this->redirect('/bankimport');
        }

        $delimiter = $request->str('delimiter', ';') ?: ';';
        $hasHeader = $request->bool('has_header');
        $colDate   = $request->int('col_date', 1) - 1;
        $colAmount = $request->int('col_amount', 4) - 1;
        $colPurpose = $request->int('col_purpose', 3) - 1;

        $content = (string) file_get_contents($file['tmp_name']);
        // Encoding auf UTF-8 normalisieren (Bank-Exporte oft ISO-8859-1).
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $rows  = [];
        foreach ($lines as $idx => $line) {
            if ($line === '') {
                continue;
            }
            if ($idx === 0 && $hasHeader) {
                continue;
            }
            $cols = str_getcsv($line, $delimiter);
            $date    = trim((string) ($cols[$colDate] ?? ''));
            $amount  = Format::toCents((string) ($cols[$colAmount] ?? '0'));
            $purpose = trim((string) ($cols[$colPurpose] ?? ''));
            if ($amount === 0 && $purpose === '') {
                continue;
            }
            $rows[] = [
                'date'    => self::normalizeDate($date),
                'amount'  => $amount,
                'purpose' => mb_strimwidth($purpose, 0, 120, ''),
            ];
        }

        if ($rows === []) {
            Session::flash('error', 'Keine verwertbaren Zeilen gefunden. Bitte Spalten/Trennzeichen prüfen.');
            $this->redirect('/bankimport');
        }

        // Zahlungseingänge (positive Beträge) offenen Rechnungen zuordnen.
        $open = (new InvoiceRepository())->openForMatching();
        foreach ($rows as &$row) {
            $row['match_id']    = 0;
            $row['match_label'] = '';
            if ($row['amount'] <= 0) {
                continue;
            }
            $match = self::matchInvoice($row, $open);
            if ($match !== null) {
                $row['match_id']    = $match['id'];
                $row['match_label'] = $match['number'] . ' · offen ' . Format::money($match['open_cents']);
            }
        }
        unset($row);

        $this->view('bankimport/preview', ['title' => 'Bankimport – Vorschau', 'rows' => $rows]);
    }

    /**
     * Findet eine passende offene Rechnung: zuerst per Rechnungsnummer im
     * Verwendungszweck, dann per exakt offenem Betrag.
     *
     * @param array{amount:int,purpose:string} $row
     * @param array<int,array{id:int,number:string,open_cents:int}> $open
     * @return array{id:int,number:string,open_cents:int}|null
     */
    private static function matchInvoice(array $row, array $open): ?array
    {
        foreach ($open as $o) {
            if ($o['number'] !== '' && stripos($row['purpose'], $o['number']) !== false) {
                return $o;
            }
        }
        foreach ($open as $o) {
            if ($o['open_cents'] === $row['amount']) {
                return $o;
            }
        }
        return null;
    }

    public function commit(Request $request): void
    {
        $this->verifyCsrf($request);

        $dates    = (array) ($request->post['row_date'] ?? []);
        $amounts  = (array) ($request->post['row_amount'] ?? []);
        $purposes = (array) ($request->post['row_purpose'] ?? []);
        $selected = (array) ($request->post['row_select'] ?? []);
        $payRows  = (array) ($request->post['row_pay'] ?? []);
        $matches  = (array) ($request->post['row_match'] ?? []);

        // --- Zahlungseingänge offenen Rechnungen zuordnen ---
        $invRepo     = new InvoiceRepository();
        $paymentRepo = new PaymentRepository();
        $paid        = 0;
        foreach ($payRows as $i) {
            $i      = (int) $i;
            $invId  = (int) ($matches[$i] ?? 0);
            $amount = (int) ($amounts[$i] ?? 0);
            if ($invId <= 0 || $amount <= 0) {
                continue;
            }
            $inv = $invRepo->find($invId);
            if ($inv === null || (int) $inv['is_locked'] !== 1 || $inv['status'] === 'cancelled') {
                continue;
            }
            $paidOn = (string) ($dates[$i] ?? date('Y-m-d')) ?: date('Y-m-d');
            $pid = $paymentRepo->createPayment($invId, $paidOn, $amount, 'Bankimport', (string) ($purposes[$i] ?? ''));
            LedgerService::recordIncome($paidOn, $amount, 'payment', $pid, 'Umsatzerlöse', 'Zahlungseingang Rechnung ' . $inv['number']);
            $invRepo->recalcPaymentStatus($invId);
            $paid++;
        }

        $repo    = new ExpenseRepository();
        $count   = 0;
        foreach ($selected as $i) {
            $i      = (int) $i;
            $amount = (int) ($amounts[$i] ?? 0);
            if ($amount >= 0) {
                continue; // nur Ausgaben (negative Beträge) importieren
            }
            $id = $repo->createFromInput([
                'expense_date' => (string) ($dates[$i] ?? date('Y-m-d')),
                'supplier'     => '',
                'category'     => 'Bankimport',
                'tax_category' => 'Sonstiges',
                'amount'       => Format::amount(abs($amount)),
                'status'       => 'paid',
                'method'       => 'Bankeinzug',
                'note'         => (string) ($purposes[$i] ?? ''),
            ]);
            $e = $repo->find($id);
            LedgerService::syncExpense($id, -abs($amount), $e['expense_date'], 'Bankimport', (string) ($purposes[$i] ?? 'Bankumsatz'));
            $count++;
        }

        AuditService::record('create', 'bankimport', null, null, ['imported_expenses' => $count, 'matched_payments' => $paid]);
        Session::flash('success', "{$count} Ausgabe(n) übernommen, {$paid} Zahlung(en) zugeordnet.");
        $this->redirect($paid > 0 && $count === 0 ? '/rechnungen' : '/ausgaben');
    }

    private static function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        // dd.mm.yyyy oder dd.mm.yy -> yyyy-mm-dd
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $raw, $m)) {
            $year = (int) $m[3];
            if ($year < 100) {
                $year += 2000;
            }
            return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        return date('Y-m-d');
    }
}
