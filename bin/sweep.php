<?php

declare(strict_types=1);

/**
 * Wartungs-Sweep für den Cron-Betrieb. Aktualisiert fällige Rechnungen auf den
 * Status 'overdue'. Im Browser geschieht das beim Dashboard-Aufruf; per Cron
 * bleibt der Status auch ohne Login aktuell (z.B. für korrekte Auswertungen).
 *
 * Aufruf:  php bin/sweep.php
 * Cron:    30 2 * * *  cd /pfad/zu/nova && php bin/sweep.php
 */

use Nova\Core\DB;
use Nova\Models\InvoiceRepository;
use Nova\Services\RecurringService;
use Nova\Services\ReminderService;
use Nova\Services\UpdateService;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
DB::init($config['db_path']);
$GLOBALS['nova_config'] = $config;

$count = (new InvoiceRepository())->markOverdue();
echo "» Sweep: {$count} Rechnung(en) auf 'überfällig' gesetzt.\n";

// Wiederkehrende Rechnungen erzeugen.
foreach (RecurringService::runDue($config) as $line) {
    echo '» Wiederkehrend: ' . $line . "\n";
}

// Automatische Zahlungserinnerungen (falls aktiviert).
foreach (ReminderService::sendAuto($config) as $line) {
    echo '» Erinnerung: ' . $line . "\n";
}

// Update-Cache auffrischen (für die Anzeige im Tool).
$u = UpdateService::check(true);
echo '» Update-Prüfung: ' . (!empty($u['has_update']) ? ('neue Version ' . $u['latest']) : 'aktuell') . "\n";
