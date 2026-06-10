<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Lädt ein Backup-ZIP in konfigurierte Cloud-Ziele hoch (WebDAV, S3-kompatibel,
 * FTP/FTPS, Dropbox). Jedes Ziel ist unabhängig und nur aktiv, wenn die nötigen
 * Felder gesetzt sind. Reines cURL – keine externen Abhängigkeiten.
 */
final class CloudBackupService
{
    /** Ist mindestens ein Cloud-Ziel konfiguriert? @param array<string,mixed> $s */
    public static function anyConfigured(array $s): bool
    {
        return trim((string) ($s['backup_webdav_url'] ?? '')) !== ''
            || trim((string) ($s['backup_s3_bucket'] ?? '')) !== ''
            || trim((string) ($s['backup_ftp_host'] ?? '')) !== ''
            || trim((string) ($s['backup_dropbox_token'] ?? '')) !== '';
    }

    /**
     * Lädt die Datei in alle konfigurierten Ziele. Gibt Protokollzeilen zurück.
     *
     * @param array<string,mixed> $s
     * @return array<int,string>
     */
    public static function upload(string $zipPath, array $s): array
    {
        $log      = [];
        $filename = basename($zipPath);

        if (trim((string) ($s['backup_webdav_url'] ?? '')) !== '') {
            $log[] = self::guard('WebDAV', fn () => self::webdav($zipPath, $filename, $s));
        }
        if (trim((string) ($s['backup_s3_bucket'] ?? '')) !== '') {
            $log[] = self::guard('S3', fn () => self::s3($zipPath, $filename, $s));
        }
        if (trim((string) ($s['backup_ftp_host'] ?? '')) !== '') {
            $log[] = self::guard('FTP', fn () => self::ftp($zipPath, $filename, $s));
        }
        if (trim((string) ($s['backup_dropbox_token'] ?? '')) !== '') {
            $log[] = self::guard('Dropbox', fn () => self::dropbox($zipPath, $filename, $s));
        }
        return $log;
    }

    /** Fängt Fehler je Ziel ab, damit ein Ziel die anderen nicht blockiert. */
    private static function guard(string $name, callable $fn): string
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return "Cloud {$name}: FEHLER – " . $e->getMessage();
        }
    }

    /** @param array<string,mixed> $s */
    private static function webdav(string $zipPath, string $filename, array $s): string
    {
        $base = rtrim((string) $s['backup_webdav_url'], '/') . '/';
        $url  = $base . rawurlencode($filename);
        $fh   = fopen($zipPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Datei nicht lesbar.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $fh,
            CURLOPT_INFILESIZE     => filesize($zipPath),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERPWD        => (string) $s['backup_webdav_user'] . ':' . (string) $s['backup_webdav_pass'],
            CURLOPT_HTTPHEADER     => ['Content-Type: application/zip'],
        ]);
        $ok     = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($ok === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException($err !== '' ? $err : ('HTTP ' . $status));
        }
        return 'Cloud WebDAV: hochgeladen (' . $filename . ').';
    }

    /** @param array<string,mixed> $s */
    private static function ftp(string $zipPath, string $filename, array $s): string
    {
        $host = (string) $s['backup_ftp_host'];
        $port = (int) ($s['backup_ftp_port'] ?? 21) ?: 21;
        $path = trim((string) ($s['backup_ftp_path'] ?? ''), '/');
        $scheme = 'ftp';
        $url  = $scheme . '://' . $host . ':' . $port . '/' . ($path !== '' ? $path . '/' : '') . $filename;
        $fh   = fopen($zipPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Datei nicht lesbar.');
        }
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_UPLOAD            => true,
            CURLOPT_INFILE            => $fh,
            CURLOPT_INFILESIZE        => filesize($zipPath),
            CURLOPT_TIMEOUT           => 180,
            CURLOPT_USERPWD           => (string) $s['backup_ftp_user'] . ':' . (string) $s['backup_ftp_pass'],
            CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR,
        ];
        if (!empty($s['backup_ftp_tls'])) {
            $opts[CURLOPT_USE_SSL] = CURLUSESSL_ALL; // explizites FTPS (FTP über TLS)
        }
        curl_setopt_array($ch, $opts);
        $ok  = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($ok === false) {
            throw new \RuntimeException($err !== '' ? $err : 'Upload fehlgeschlagen.');
        }
        return 'Cloud FTP: hochgeladen (' . $filename . ').';
    }

    /** @param array<string,mixed> $s */
    private static function dropbox(string $zipPath, string $filename, array $s): string
    {
        $folder = '/' . trim((string) ($s['backup_dropbox_path'] ?? ''), '/');
        $target = rtrim($folder, '/') . '/' . $filename;
        $arg = json_encode(['path' => $target, 'mode' => 'add', 'autorename' => true, 'mute' => true]);
        $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => file_get_contents($zipPath),
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . (string) $s['backup_dropbox_token'],
                'Dropbox-API-Arg: ' . $arg,
                'Content-Type: application/octet-stream',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            $msg = $err !== '' ? $err : ('HTTP ' . $status . ' ' . substr((string) $body, 0, 200));
            throw new \RuntimeException($msg);
        }
        return 'Cloud Dropbox: hochgeladen (' . $filename . ').';
    }

    /**
     * S3-kompatibler PUT mit AWS Signature V4 (Path-Style). Funktioniert mit AWS,
     * Backblaze B2 (S3-API), Wasabi, Hetzner Object Storage, MinIO u. a.
     *
     * @param array<string,mixed> $s
     */
    private static function s3(string $zipPath, string $filename, array $s): string
    {
        $endpoint = (string) $s['backup_s3_endpoint'];
        $region   = (string) ($s['backup_s3_region'] ?: 'us-east-1');
        $bucket   = (string) $s['backup_s3_bucket'];
        $key      = (string) $s['backup_s3_key'];
        $secret   = (string) $s['backup_s3_secret'];
        $prefix   = trim((string) ($s['backup_s3_prefix'] ?? ''), '/');
        if ($endpoint === '' || $bucket === '' || $key === '' || $secret === '') {
            throw new \RuntimeException('Konfiguration unvollständig.');
        }

        // Host aus Endpoint ableiten (mit oder ohne Schema).
        $host = preg_replace('#^https?://#', '', rtrim($endpoint, '/')) ?? $endpoint;
        $objectKey = ($prefix !== '' ? $prefix . '/' : '') . $filename;
        // Path-Style: /bucket/objectKey – jedes Segment einzeln URL-kodieren.
        $canonicalUri = '/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectKey)));

        $payload     = (string) file_get_contents($zipPath);
        $payloadHash = hash('sha256', $payload);
        $amzDate     = gmdate('Ymd\THis\Z');
        $dateStamp   = gmdate('Ymd');

        $canonicalHeaders = "host:{$host}\n"
            . "x-amz-content-sha256:{$payloadHash}\n"
            . "x-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = "PUT\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";

        $scope        = "{$dateStamp}/{$region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$scope}\n" . hash('sha256', $canonicalRequest);

        $kDate    = hash_hmac('sha256', $dateStamp, 'AWS4' . $secret, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$key}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init('https://' . $host . $canonicalUri);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_HTTPHEADER     => [
                'Host: ' . $host,
                'x-amz-date: ' . $amzDate,
                'x-amz-content-sha256: ' . $payloadHash,
                'Authorization: ' . $authorization,
                'Content-Type: application/zip',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            $msg = $err !== '' ? $err : ('HTTP ' . $status . ' ' . substr((string) $body, 0, 200));
            throw new \RuntimeException($msg);
        }
        return 'Cloud S3: hochgeladen (' . $objectKey . ').';
    }
}
