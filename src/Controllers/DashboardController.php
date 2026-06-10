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

        // Kleinunternehmer-Umsatzgrenze.
        $settings = (new CompanySettingsRepository())->get();
        $kuActive = (int) $settings['is_kleinunternehmer'] === 1;
        $kuPercent = self::KU_LIMIT_CENTS > 0 ? min(100, (int) round($summary['income'] / self::KU_LIMIT_CENTS * 100)) : 0;

        $this->view('dashboard/index', [
            'title'           => 'Dashboard',
            'year'            => $year,
            'summary'         => $summary,
            'months'          => EuerService::byMonth($year),
            'openInvoices'    => $openInvoices,
            'openInvoicesSum' => $openInvoicesSum,
            'overdue'         => $overdue,
            'openExpenses'    => $openExpenses,
            'missingReceipts' => $missingReceipts,
            'customerCount'   => $customerCount,
            'kuActive'        => $kuActive,
            'kuLimit'         => self::KU_LIMIT_CENTS,
            'kuPercent'       => $kuPercent,
        ]);
    }
}
