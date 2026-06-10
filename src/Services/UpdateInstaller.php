<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Voll-automatischer 1-Klick-Updater.
 *
 * Ablauf (sicherheitsorientiert):
 *   1. Backup anlegen (DB + Uploads) – IMMER zuerst.
 *   2. Wartungs-Flag setzen (kurzer Schutz vor parallelen Zugriffen).
 *   3. Release-ZIP herunterladen und entpacken.
 *   4. Programmdateien überlagern – storage/, config.php und die DB bleiben unangetastet.
 *   5. Datenbank-Migrationen ausführen.
 *
 * Bewusst nicht-destruktiv: vorhandene Dateien werden überschrieben, aber keine
 * gelöscht. Bei jedem Fehler bricht der Vorgang ab; das Backup aus Schritt 1
 * erlaubt die Wiederherstellung.
 */
final class UpdateInstaller
{
    /** Top-Level-Pfade, die NIE überschrieben werden. */
    private const PROTECTED = ['storage', 'config.php', '.git', '.env', '.htaccess'];

    /**
     * @param array<string,mixed> $config
     * @return array<int,string> Protokollzeilen
     */
    public static function run(string $zipUrl, array $config): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("PHP-Extension 'zip' ist nicht verfügbar.");
        }
        $root  = rtrim((string) $config['paths']['root'], '/');
        $cache = (string) $config['paths']['storage'] . '/cache';
        if (!is_dir($cache) && !@mkdir($cache, 0775, true) && !is_dir($cache)) {
            throw new \RuntimeException('Cache-Verzeichnis nicht beschreibbar.');
        }
        $log = [];

        // 1. Backup zuerst.
        $backup = BackupService::create($config);
        $log[]  = 'Backup angelegt: ' . basename($backup);

        $flag = (string) $config['paths']['storage'] . '/maintenance.flag';
        @file_put_contents($flag, (string) time());

        $zipPath     = $cache . '/update-download.zip';
        $extractRoot = $cache . '/update-extract';

        try {
            // 2. Herunterladen.
            self::download($zipUrl, $zipPath);
            $log[] = 'Release heruntergeladen';

            // 3. Entpacken.
            self::rrmdir($extractRoot);
            @mkdir($extractRoot, 0775, true);
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Heruntergeladenes ZIP konnte nicht geöffnet werden.');
            }
            $zip->extractTo($extractRoot);
            $zip->close();

            // GitHub-Zipballs entpacken in einen einzelnen Unterordner.
            $src = self::resolveSourceRoot($extractRoot);
            self::assertLooksLikeNova($src);

            // 4. Dateien überlagern.
            $count = self::copyOver($src, $root);
            $log[] = $count . ' Datei(en) aktualisiert';

            // 5. Migrationen.
            $migrated = MigrationRunner::run($config['paths']['migrations']);
            $log[] = $migrated === [] ? 'keine neuen Migrationen' : count($migrated) . ' Migration(en) angewendet';
        } finally {
            @unlink($flag);
            @unlink($zipPath);
            self::rrmdir($extractRoot);
        }

        return $log;
    }

    private static function download(string $url, string $target): void
    {
        $fh = fopen($target, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Download-Datei nicht beschreibbar.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => ['User-Agent: Nova-Updater', 'Accept: application/octet-stream'],
        ]);
        $ok     = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if ($ok === false || $status >= 400 || (int) (filesize($target) ?: 0) === 0) {
            @unlink($target);
            throw new \RuntimeException("Download fehlgeschlagen (HTTP {$status}) {$err}");
        }
    }

    /** Findet das eigentliche Quellverzeichnis (ein einzelner Unterordner bei Zipballs). */
    private static function resolveSourceRoot(string $extractRoot): string
    {
        $entries = array_values(array_filter(
            scandir($extractRoot) ?: [],
            static fn ($e) => $e !== '.' && $e !== '..'
        ));
        if (count($entries) === 1 && is_dir($extractRoot . '/' . $entries[0])) {
            return $extractRoot . '/' . $entries[0];
        }
        return $extractRoot;
    }

    /** Sicherheitscheck: sieht das entpackte Paket wie Nova aus? */
    private static function assertLooksLikeNova(string $src): void
    {
        if (!is_dir($src . '/src') || !is_file($src . '/public/index.php')) {
            throw new \RuntimeException('Das Release sieht nicht wie eine gültige Nova-Installation aus (src/ bzw. public/index.php fehlt).');
        }
    }

    /**
     * Kopiert $src rekursiv nach $dest und überschreibt vorhandene Dateien.
     * Geschützte Top-Level-Pfade werden ausgelassen. Gibt die Dateianzahl zurück.
     */
    private static function copyOver(string $src, string $dest): int
    {
        $count = 0;
        foreach (scandir($src) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, self::PROTECTED, true)) {
                continue;
            }
            $count += self::copyRecursive($src . '/' . $entry, $dest . '/' . $entry);
        }
        return $count;
    }

    private static function copyRecursive(string $from, string $to): int
    {
        if (is_dir($from)) {
            if (!is_dir($to) && !@mkdir($to, 0775, true) && !is_dir($to)) {
                throw new \RuntimeException("Verzeichnis nicht anlegbar: {$to}");
            }
            $count = 0;
            foreach (scandir($from) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $count += self::copyRecursive($from . '/' . $entry, $to . '/' . $entry);
            }
            return $count;
        }
        if (!@copy($from, $to)) {
            throw new \RuntimeException("Datei nicht schreibbar: {$to}");
        }
        return 1;
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
