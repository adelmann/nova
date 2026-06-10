<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Models\CompanySettingsRepository;
use Nova\Services\AuditService;
use Nova\Services\BackupService;

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

        try {
            $log = BackupService::runFromSettings($settings, $GLOBALS['nova_config']);
            AuditService::record('backup', 'company_settings', 1, null, ['via' => 'cron']);
            echo "OK\n" . implode("\n", $log) . "\n";
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'FEHLER: ' . $e->getMessage() . "\n";
        }
    }
}
