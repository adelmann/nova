<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Hilfsfunktionen für HTTP-Antworten.
 */
final class Response
{
    /**
     * Verhindert das Zwischenspeichern dynamischer Antworten durch Browser und
     * vorgelagerte Proxies/Caches (sonst werden z.B. veraltete PDFs oder
     * Auswertungen ausgeliefert).
     */
    public static function noCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        self::noCache();
        header('Content-Type: text/html; charset=UTF-8');
        echo $body;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $to);
        exit;
    }

    public static function notFound(string $message = 'Seite nicht gefunden'): void
    {
        self::html('<h1>404</h1><p>' . View::e($message) . '</p>', 404);
    }

    public static function forbidden(string $message = 'Dafür fehlt dir die Berechtigung.'): void
    {
        self::html(
            '<div style="font-family:system-ui;max-width:520px;margin:12vh auto;padding:0 20px;text-align:center;color:#1f2733">'
            . '<h1 style="font-size:48px;margin:0">403</h1>'
            . '<p style="color:#6b7685">' . View::e($message) . '</p>'
            . '<p><a href="/" style="color:#2f6fed">Zur Startseite</a></p></div>',
            403
        );
    }

    public static function csv(string $filename, string $content): never
    {
        self::noCache();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // BOM für Excel-Kompatibilität
        echo $content;
        exit;
    }

    public static function download(string $filePath, string $downloadName, string $mime): never
    {
        self::noCache();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        readfile($filePath);
        exit;
    }

    public static function inline(string $filePath, string $mime): never
    {
        self::noCache();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('Content-Length: ' . (string) filesize($filePath));
        readfile($filePath);
        exit;
    }
}
