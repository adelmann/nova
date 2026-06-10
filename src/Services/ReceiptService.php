<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Validierung und sichere Ablage hochgeladener Dateien (Belege, Logo).
 * Dateien landen außerhalb des Web-Roots; Auslieferung nur über
 * authentifizierte Download-Routen.
 */
final class ReceiptService
{
    /**
     * Validiert einen Upload anhand der erlaubten MIME-Typen und Größe.
     *
     * @param array<string,mixed> $file  Eintrag aus $_FILES
     * @return array{mime:string,ext:string,size:int}
     */
    public static function validate(array $file, ?array $allowedMimes = null): array
    {
        $config       = $GLOBALS['nova_config'] ?? require dirname(__DIR__, 2) . '/config.php';
        $allowedMimes ??= $config['uploads']['allowed_mimes'];
        $maxBytes     = (int) $config['uploads']['max_bytes'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload fehlgeschlagen (Fehlercode ' . ($file['error'] ?? '?') . ').');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Ungültiger Upload.');
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > $maxBytes) {
            throw new \RuntimeException('Datei ist leer oder größer als ' . (int) ($maxBytes / 1024 / 1024) . ' MB.');
        }

        // MIME serverseitig per finfo bestimmen, nicht dem Client vertrauen.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);

        if (!isset($allowedMimes[$mime])) {
            throw new \RuntimeException('Dateityp nicht erlaubt: ' . $mime);
        }

        return ['mime' => $mime, 'ext' => $allowedMimes[$mime], 'size' => $size];
    }

    /**
     * Speichert einen Beleg in storage/receipts und gibt Metadaten zurück.
     *
     * @param array<string,mixed> $file
     * @return array{stored_path:string,original_name:string,mime:string,size_bytes:int,sha256:string}
     */
    public static function storeReceipt(array $file): array
    {
        $config = $GLOBALS['nova_config'] ?? require dirname(__DIR__, 2) . '/config.php';
        $meta   = self::validate($file);

        $subdir = date('Y') . '/' . date('m');
        $dir    = $config['paths']['receipts'] . '/' . $subdir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $sha      = hash_file('sha256', $file['tmp_name']);
        $filename = bin2hex(random_bytes(8)) . '.' . $meta['ext'];
        $target   = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        return [
            'stored_path'   => $subdir . '/' . $filename,
            'original_name' => self::cleanName((string) ($file['name'] ?? 'beleg')),
            'mime'          => $meta['mime'],
            'size_bytes'    => $meta['size'],
            'sha256'        => $sha,
        ];
    }

    /**
     * Speichert ein Logo in storage/logos und gibt den relativen Pfad zurück.
     *
     * @param array<string,mixed> $file
     */
    public static function storeLogo(array $file): string
    {
        $config = $GLOBALS['nova_config'] ?? require dirname(__DIR__, 2) . '/config.php';
        // Logo nur als Bild zulassen.
        $meta = self::validate($file, ['image/jpeg' => 'jpg', 'image/png' => 'png']);

        $dir = $config['paths']['logos'];
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filename = 'logo.' . $meta['ext'];
        $target   = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Logo konnte nicht gespeichert werden.');
        }

        return $filename;
    }

    /**
     * Normalisiert einen $_FILES-Eintrag eines Mehrfach-Uploads (name="x[]")
     * in eine Liste einzelner Datei-Arrays. Leere Felder werden übersprungen.
     *
     * @param array<string,mixed>|null $entry
     * @return array<int,array<string,mixed>>
     */
    public static function normalizeUploads(?array $entry): array
    {
        if ($entry === null || !isset($entry['name'])) {
            return [];
        }

        // Einzel-Upload (name="x"): direkt zurückgeben, sofern eine Datei da ist.
        if (!is_array($entry['name'])) {
            return ($entry['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$entry];
        }

        // Mehrfach-Upload (name="x[]"): pro Index zusammensetzen.
        $files = [];
        foreach ($entry['name'] as $i => $name) {
            if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => $name,
                'type'     => $entry['type'][$i] ?? '',
                'tmp_name' => $entry['tmp_name'][$i] ?? '',
                'error'    => $entry['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $entry['size'][$i] ?? 0,
            ];
        }
        return $files;
    }

    private static function cleanName(string $name): string
    {
        $name = basename($name);
        return preg_replace('/[^\w.\-]+/u', '_', $name) ?? 'beleg';
    }
}
