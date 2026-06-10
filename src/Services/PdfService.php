<?php

declare(strict_types=1);

namespace Nova\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Nova\Core\View;

/**
 * Erzeugt PDFs aus PHP-Templates (views/pdf/*) per Dompdf.
 */
final class PdfService
{
    /**
     * Rendert ein PDF-Template und gibt die rohen PDF-Bytes zurück.
     *
     * @param array<string,mixed> $data
     */
    public static function renderToString(string $template, array $data = []): string
    {
        $html = View::render($template, $data, layout: null);

        $options = new Options();
        $options->set('isRemoteEnabled', true);   // lokales Logo via file:// / data:
        $options->set('defaultFont', 'DejaVu Sans'); // Unicode/Umlaute
        $options->set('chroot', dirname(__DIR__, 2)); // Dateizugriff auf Projektordner

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Rendert ein PDF und schreibt es auf die Platte.
     *
     * @param array<string,mixed> $data
     */
    public static function renderToFile(string $template, array $data, string $absolutePath): void
    {
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($absolutePath, self::renderToString($template, $data));
    }

    /**
     * Rendert ein PDF und sendet es direkt an den Browser (inline).
     *
     * @param array<string,mixed> $data
     */
    public static function stream(string $template, array $data, string $filename): never
    {
        $pdf = self::renderToString($template, $data);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . (string) strlen($pdf));
        echo $pdf;
        exit;
    }
}
