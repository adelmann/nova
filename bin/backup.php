<?php

declare(strict_types=1);

/**
 * Backup-Runner für den CLI-/Cron-Betrieb. Erstellt ein (optional
 * passwortgeschütztes) ZIP aus DB-Snapshot und hochgeladenen Dateien und
 * verteilt es gemäß den Einstellungen (E-Mail / Zielverzeichnis).
 *
 * Aufruf:  php bin/backup.php
 * Cron:    0 3 * * *  cd /pfad/zu/nova && php bin/backup.php >> storage/backups/backup.log 2>&1
 *
 * Alternativ steht ein token-geschützter Web-Endpoint zur Verfügung
 * (siehe Einstellungen → Datensicherung), falls kein CLI-Cron möglich ist.
 */

use Nova\Core\DB;
use Nova\Models\CompanySettingsRepository;
use Nova\Services\BackupService;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
DB::init($config['db_path']);

try {
    $settings = (new CompanySettingsRepository())->get();
    foreach (BackupService::runFromSettings($settings, $config) as $line) {
        echo '» ' . $line . "\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'Backup fehlgeschlagen: ' . $e->getMessage() . "\n");
    exit(1);
}
