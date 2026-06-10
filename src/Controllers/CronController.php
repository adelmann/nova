<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Models\CompanySettingsRepository;
use Nova\Models\InvoiceRepository;
use Nova\Services\AuditService;
use Nova\Services\BackupService;
use Nova\Services\DepreciationService;
use Nova\Services\RecurringExpenseService;
use Nova\Services\RecurringService;
use Nova\Services\ReminderService;

/**
 * Token-geschützte Endpoints für externe Cron-Dienste (z.B. der Cron des
 * Hosters per wget/curl), falls kein CLI-Cron verfügbar ist.
 */
final class CronController extends Controller
{
    public function backup(Request $request): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $settings = (new CompanySettingsRepository())->get();
        $expected = (string) ($settings['backup_token'] ?? '');
        $provided = $request->str('token');

        // Ohne konfiguriertes Token ist der Endpoint deaktiviert.
        if ($expected === '') {
            http_response_code(503);
            echo "Backup-Cron ist nicht aktiviert (kein Token gesetzt).\n";
            return;
        }
        if ($provided === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            echo "Zugriff verweigert (ungültiges Token).\n";
            return;
        }

        // Häufigkeit drosseln: nur anlegen, wenn fällig (sofern nicht force=1).
        if (!$request->bool('force') && !BackupService::isBackupDue($settings, $GLOBALS['nova_config'])) {
            $interval = (int) ($settings['backup_interval_hours'] ?? 24);
            echo "Übersprungen: letztes Backup jünger als {$interval} h. (Mit ?force=1 erzwingbar.)\n";
            return;
        }

        try {
            $log = BackupService::runFromSettings($settings, $GLOBALS['nova_config']);
            AuditService::record('backup', 'company_settings', 1, null, ['via' => 'cron']);
            echo "OK\n" . implode("\n", $log) . "\n";
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'FEHLER: ' . $e->getMessage() . "\n";
        }
    }

    /**
     * Wartungs-Sweep über HTTP (Alternative zu bin/sweep.php, falls kein
     * CLI-Cron verfügbar ist): überfällige Rechnungen markieren, wiederkehrende
     * Rechnungen/Ausgaben erzeugen, fällige AfA buchen, Auto-Mahnungen senden.
     * Token wie beim Backup-Cron. Alle Aufgaben sind idempotent.
     */
    public function sweep(Request $request): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $settings = (new CompanySettingsRepository())->get();
        $expected = (string) ($settings['backup_token'] ?? '');
        $provided = $request->str('token');
        if ($expected === '') {
            http_response_code(503);
            echo "Wartungs-Cron ist nicht aktiviert (kein Token gesetzt – unter Datensicherung erzeugbar).\n";
            return;
        }
        if ($provided === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            echo "Zugriff verweigert (ungültiges Token).\n";
            return;
        }

        $config = $GLOBALS['nova_config'];
        try {
            $count = (new InvoiceRepository())->markOverdue();
            echo "OK\n» {$count} Rechnung(en) auf überfällig gesetzt.\n";
            foreach (RecurringService::runDue($config) as $line) { echo '» Wiederkehrend: ' . $line . "\n"; }
            foreach (RecurringExpenseService::runDue() as $line) { echo '» Dauerausgabe: ' . $line . "\n"; }
            foreach (DepreciationService::bookDueYears() as $line) { echo '» AfA: ' . $line . "\n"; }
            foreach (ReminderService::sendAuto($config) as $line) { echo '» Erinnerung: ' . $line . "\n"; }
            AuditService::record('sweep', 'company_settings', 1, null, ['via' => 'cron']);
            echo "Fertig.\n";
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'FEHLER: ' . $e->getMessage() . "\n";
        }
    }
}
