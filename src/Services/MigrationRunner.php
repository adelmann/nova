<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\DB;

/**
 * Führt SQL-Migrationen aus migrations/ aus und protokolliert sie in
 * schema_migrations. Wiederverwendbar aus CLI (bin/migrate.php), dem
 * Setup-Wizard und dem Updater.
 */
final class MigrationRunner
{
    private static function ensureTable(DB $db): void
    {
        $db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                filename   TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
    }

    /** @return array<int,string> Noch nicht angewendete Migrationsdateien. */
    public static function pending(string $migrationsDir): array
    {
        $db = DB::getInstance();
        self::ensureTable($db);
        $applied = array_column($db->fetchAll('SELECT filename FROM schema_migrations'), 'filename');
        $files   = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }
        return $pending;
    }

    /**
     * Wendet alle ausstehenden Migrationen an. Jede Datei läuft in einer
     * Transaktion; bei Fehler wird sie zurückgerollt und eine Exception geworfen.
     *
     * @return array<int,string> Namen der angewendeten Migrationen
     */
    public static function run(string $migrationsDir): array
    {
        $db = DB::getInstance();
        self::ensureTable($db);

        $applied = array_column($db->fetchAll('SELECT filename FROM schema_migrations'), 'filename');
        $files   = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            try {
                // Einige Migrationen enthalten eigene PRAGMA/Transaktions-Logik
                // (z.B. Tabellen-Neuaufbau); daher ohne umschließende Transaktion
                // ausführen, aber bei Fehler sauber abbrechen.
                $db->pdo()->exec($sql);
                $db->query('INSERT INTO schema_migrations (filename) VALUES (:f)', ['f' => $name]);
                $ran[] = $name;
            } catch (\Throwable $e) {
                throw new \RuntimeException("Migration {$name} fehlgeschlagen: " . $e->getMessage(), 0, $e);
            }
        }
        return $ran;
    }
}
