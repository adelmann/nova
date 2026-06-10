<?php

declare(strict_types=1);

/**
 * Migrations-Runner (CLI). Führt alle noch nicht angewendeten *.sql-Dateien aus
 * migrations/ aus und protokolliert sie in der Tabelle schema_migrations.
 *
 * Aufruf:  php bin/migrate.php
 */

use Nova\Core\DB;
use Nova\Services\MigrationRunner;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
DB::init($config['db_path']);

try {
    $ran = MigrationRunner::run($config['paths']['migrations']);
} catch (\Throwable $e) {
    fwrite(STDERR, '  FEHLER: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach ($ran as $name) {
    echo "» Wende Migration an: {$name}\n";
}
echo $ran === []
    ? "Keine ausstehenden Migrationen. Datenbank ist aktuell.\n"
    : 'Fertig. ' . count($ran) . " Migration(en) angewendet.\n";
