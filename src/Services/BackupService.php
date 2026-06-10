<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\DB;

/**
 * Erstellt Datensicherungen (SQLite-DB-Snapshot + hochgeladene Dateien) als
 * ZIP-Archiv, optional AES-256-verschlüsselt mit Passwort. Kann das Archiv
 * zusätzlich per E-Mail versenden und/oder in ein Zielverzeichnis kopieren.
 *
 * Wird sowohl vom CLI-Runner (bin/backup.php) als auch vom token-geschützten
 * Web-Endpoint (CronController) genutzt.
 */
final class BackupService
{
    private const KEEP = 14;

    /**
     * Erstellt das Backup-ZIP und gibt den absoluten Pfad zurück.
     *
     * @param array<string,mixed> $config  globale Konfiguration (paths, …)
     * @param string              $password leer = unverschlüsselt
     *
     * @throws \RuntimeException
     */
    public static function create(array $config, string $password = '', string $stamp = ''): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("PHP-Extension 'zip' ist nicht verfügbar.");
        }

        $backupDir = (string) $config['paths']['backups'];
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
            throw new \RuntimeException("Backup-Verzeichnis nicht beschreibbar: {$backupDir}");
        }

        $stamp   = $stamp !== '' ? $stamp : date('Y-m-d_His');
        $zipPath = $backupDir . '/nova-' . $stamp . '.zip';

        // 1. Konsistenter DB-Snapshot (auch bei WAL), ohne den Betrieb zu sperren.
        $dbSnapshot = $backupDir . '/.snapshot-' . $stamp . '.sqlite';
        DB::getInstance()->pdo()->exec("VACUUM INTO '" . str_replace("'", "''", $dbSnapshot) . "'");

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($dbSnapshot);
            throw new \RuntimeException("ZIP konnte nicht erstellt werden: {$zipPath}");
        }

        $encrypt = $password !== '' && self::encryptionSupported();
        if ($encrypt) {
            $zip->setPassword($password);
        }

        $add = static function (string $absPath, string $entry) use ($zip, $encrypt): void {
            $zip->addFile($absPath, $entry);
            if ($encrypt) {
                $zip->setEncryptionName($entry, \ZipArchive::EM_AES_256);
            }
        };

        $add($dbSnapshot, 'db/nova.sqlite');

        $folders = [
            'receipts' => $config['paths']['receipts'] ?? '',
            'invoices' => $config['paths']['invoices'] ?? '',
            'logos'    => $config['paths']['logos'] ?? '',
        ];
        foreach ($folders as $label => $dir) {
            if ($dir === '' || !is_dir($dir)) {
                continue;
            }
            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            ) as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $entry = $label . '/' . ltrim(substr($file->getPathname(), strlen((string) $dir)), '/\\');
                $add($file->getPathname(), $entry);
            }
        }

        $zip->close();
        @unlink($dbSnapshot);

        return $zipPath;
    }

    /**
     * Vollständiger, einstellungsgesteuerter Lauf: erstellen, optional per Mail
     * versenden und/oder in ein Verzeichnis kopieren, alte Backups aufräumen.
     *
     * @param array<string,mixed> $settings company_settings
     * @param array<string,mixed> $config
     * @return array<int,string> Protokollzeilen
     */
    public static function runFromSettings(array $settings, array $config): array
    {
        $log      = [];
        $password = (string) ($settings['backup_password'] ?? '');

        $zipPath = self::create($config, $password);
        $sizeMb  = round((int) (filesize($zipPath) ?: 0) / 1024 / 1024, 2);
        $enc     = $password !== '' && self::encryptionSupported() ? 'verschlüsselt' : 'unverschlüsselt';
        $log[]   = 'Backup erstellt: ' . basename($zipPath) . " ({$sizeMb} MB, {$enc})";

        if ($password !== '' && !self::encryptionSupported()) {
            $log[] = 'WARNUNG: ZIP-Verschlüsselung wird vom Server nicht unterstützt – Archiv ist UNVERSCHLÜSSELT.';
        }

        // In Zielverzeichnis kopieren (optional).
        $dir = trim((string) ($settings['backup_dir'] ?? ''));
        if ($dir !== '') {
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                $log[] = "FEHLER: Zielverzeichnis nicht beschreibbar: {$dir}";
            } elseif (@copy($zipPath, rtrim($dir, '/\\') . '/' . basename($zipPath))) {
                $log[] = "In Verzeichnis kopiert: {$dir}";
            } else {
                $log[] = "FEHLER: Kopieren nach {$dir} fehlgeschlagen.";
            }
        }

        // Per E-Mail versenden (optional).
        $email = trim((string) ($settings['backup_email'] ?? ''));
        if ($email !== '') {
            try {
                Mailer::send(
                    $settings,
                    $email,
                    (string) ($settings['mail_from_name'] ?? $settings['company_name'] ?? ''),
                    'Nova-Datensicherung ' . date('d.m.Y H:i'),
                    "Automatische Datensicherung im Anhang.\n\n" . implode("\n", $log),
                    [['name' => basename($zipPath), 'data' => (string) file_get_contents($zipPath), 'mime' => 'application/zip']]
                );
                $log[] = "Per E-Mail versendet an: {$email}";
            } catch (\RuntimeException $e) {
                $log[] = 'FEHLER beim E-Mail-Versand: ' . $e->getMessage();
            }
        }

        $log = array_merge($log, self::prune((string) $config['paths']['backups']));
        return $log;
    }

    /**
     * Behält nur die letzten KEEP Backups.
     *
     * @return array<int,string> Protokollzeilen
     */
    public static function prune(string $backupDir): array
    {
        $log     = [];
        $backups = glob($backupDir . '/nova-*.zip') ?: [];
        rsort($backups); // Zeitstempel im Namen => chronologisch
        foreach (array_slice($backups, self::KEEP) as $old) {
            @unlink($old);
            $log[] = 'Entfernt (Aufbewahrung ' . self::KEEP . '): ' . basename($old);
        }
        return $log;
    }

    public static function encryptionSupported(): bool
    {
        return method_exists(\ZipArchive::class, 'setEncryptionName')
            && defined('ZipArchive::EM_AES_256');
    }
}
