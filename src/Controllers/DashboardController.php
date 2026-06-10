<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Request;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\InvoiceRepository;
use Nova\Services\EuerService;

final class DashboardController extends Controller
{
    // Umsatzgrenze Kleinunternehmer §19 UStG (Vorjahr) seit 2025: 25.000 €.
    private const KU_LIMIT_CENTS = 25_000_00;

    public function index(Request $request): void
    {
        $db   = DB::getInstance();
        $year = (int) date('Y');

        // Fällige, noch offene Rechnungen auf 'overdue' setzen (ohne Cron).
        (new InvoiceRepository())->markOverdue();

        $summary = EuerService::summary($year);

        $openInvoices = (int) $db->fetchColumn("SELECT COUNT(*) FROM invoice WHERE status IN ('sent','overdue')");
        $openInvoicesSum = (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(gross_total_cents - paid_total_cents),0) FROM invoice WHERE status IN ('sent','overdue')"
        );
        // Überfällig: finalisiert, nicht bezahlt/storniert, Fälligkeit überschritten.
        $overdue = $db->fetchAll(
            "SELECT id, number, gross_total_cents - paid_total_cents AS offen, due_date
             FROM invoice
             WHERE is_locked = 1 AND status IN ('sent','overdue')
               AND due_date IS NOT NULL AND due_date < :today
             ORDER BY due_date",
            ['today' => date('Y-m-d')]
        );

        $openExpenses = (int) $db->fetchColumn("SELECT COUNT(*) FROM expense WHERE status = 'open'");
        // Bezahlte Ausgaben ohne zugeordneten Beleg.
        $missingReceipts = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM expense e
             WHERE e.status = 'paid'
               AND NOT EXISTS (SELECT 1 FROM receipt r WHERE r.linkable_type='expense' AND r.linkable_id = e.id)"
        );

        $customerCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM customer');

        // --- Liquiditätsvorschau (nächste 30 Tage) ---
        $in30 = date('Y-m-d', strtotime('+30 days'));
        // Erwartete Zuflüsse: offene Forderungen, davon innerhalb 30 Tagen fällig (inkl. überfällig).
        $receivablesOpen = (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(gross_total_cents - paid_total_cents),0) FROM invoice
             WHERE is_locked = 1 AND status IN ('sent','overdue') AND (gross_total_cents - paid_total_cents) > 0"
        );
        $inflow30 = (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(gross_total_cents - paid_total_cents),0) FROM invoice
             WHERE is_locked = 1 AND status IN ('sent','overdue') AND (gross_total_cents - paid_total_cents) > 0
               AND (due_date IS NULL OR due_date <= :in30)",
            ['in30' => $in30]
        );
        // Erwartete Abflüsse: offene Ausgaben + fällige Dauerausgaben in 30 Tagen.
        $payablesOpen = (int) $db->fetchColumn("SELECT COALESCE(SUM(amount_cents),0) FROM expense WHERE status = 'open'");
        $recurringDue30 = (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount_cents),0) FROM recurring_expense WHERE active = 1 AND next_date <= :in30",
            ['in30' => $in30]
        );
        $outflow30 = $payablesOpen + $recurringDue30;
        $liquidity = [
            'receivables_open' => $receivablesOpen,
            'inflow_30'        => $inflow30,
            'payables_open'    => $payablesOpen,
            'recurring_30'     => $recurringDue30,
            'outflow_30'       => $outflow30,
            'net_30'           => $inflow30 - $outflow30,
        ];

        // Kleinunternehmer-Umsatzgrenze.
        $settings = (new CompanySettingsRepository())->get();
        $kuActive = (int) $settings['is_kleinunternehmer'] === 1;
        $kuPercent = self::KU_LIMIT_CENTS > 0 ? min(100, (int) round($summary['income'] / self::KU_LIMIT_CENTS * 100)) : 0;

        // --- Zahlungsmethoden (Anteil, laufendes Jahr) ---
        $pmRows = $db->fetchAll(
            "SELECT COALESCE(NULLIF(method,''),'Unbekannt') AS method, SUM(amount_cents) AS s, COUNT(*) AS n
             FROM payment WHERE strftime('%Y', paid_on) = :y AND amount_cents > 0
             GROUP BY method ORDER BY s DESC",
            ['y' => (string) $year]
        );
        $pmTotal = array_sum(array_map(static fn ($r) => (int) $r['s'], $pmRows));
        $paymentMethods = array_map(static fn ($r) => [
            'method'  => (string) $r['method'],
            'sum'     => (int) $r['s'],
            'count'   => (int) $r['n'],
            'percent' => $pmTotal > 0 ? (int) round((int) $r['s'] / $pmTotal * 100) : 0,
        ], $pmRows);

        // --- Top-Kunden nach Zahlungseingang (laufendes Jahr) ---
        $topCustomers = $db->fetchAll(
            "SELECT COALESCE(NULLIF(c.company_name,''), c.contact_name) AS name, SUM(p.amount_cents) AS s
             FROM payment p JOIN invoice i ON i.id = p.invoice_id JOIN customer c ON c.id = i.customer_id
             WHERE strftime('%Y', p.paid_on) = :y AND p.amount_cents > 0
             GROUP BY c.id ORDER BY s DESC LIMIT 5",
            ['y' => (string) $year]
        );

        // --- Ausgaben nach Kategorie (Top 5, laufendes Jahr) ---
        $expenseCats = EuerService::byCategory($year)['expense'];
        arsort($expenseCats);
        $topExpenseCats = array_slice($expenseCats, 0, 5, true);

        // --- Durchschnittliche Zahldauer (Tage von Rechnungsdatum bis Zahlung) ---
        $avgPayDays = $db->fetchColumn(
            "SELECT AVG(julianday(p.paid_on) - julianday(i.invoice_date))
             FROM payment p JOIN invoice i ON i.id = p.invoice_id
             WHERE strftime('%Y', p.paid_on) = :y AND p.method != 'Skonto' AND p.amount_cents > 0",
            ['y' => (string) $year]
        );
        $avgPayDays = $avgPayDays !== null ? (int) round((float) $avgPayDays) : null;

        $this->view('dashboard/index', [
            'title'           => 'Dashboard',
            'year'            => $year,
            'paymentMethods'  => $paymentMethods,
            'topCustomers'    => $topCustomers,
            'topExpenseCats'  => $topExpenseCats,
            'avgPayDays'      => $avgPayDays,
            'summary'         => $summary,
            'months'          => EuerService::byMonth($year),
            'openInvoices'    => $openInvoices,
            'openInvoicesSum' => $openInvoicesSum,
            'overdue'         => $overdue,
            'openExpenses'    => $openExpenses,
            'missingReceipts' => $missingReceipts,
            'customerCount'   => $customerCount,
            'liquidity'       => $liquidity,
            'kuActive'        => $kuActive,
            'kuLimit'         => self::KU_LIMIT_CENTS,
            'kuPercent'       => $kuPercent,
        ]);
    }
}
