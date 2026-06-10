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
     * Liefert das Logo als Base64-`data:`-URI für die Einbettung in PDFs.
     * Transparenz (PNG-Alpha) wird gegen Weiß flachgerechnet, damit keine
     * SMask entsteht – manche mobile PDF-Viewer rendern maskierte Bilder sonst
     * nicht (Logo „fehlt"). Gibt '' zurück, wenn die Datei nicht lesbar ist.
     */
    public static function logoDataUri(string $absPath): string
    {
        $data = @file_get_contents($absPath);
        if ($data === false || $data === '') {
            return '';
        }
        // Alpha gegen Weiß flatten (robust für alle Viewer). Bei Fehlern: Original.
        if (function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring($data);
            if ($src !== false) {
                $w = imagesx($src);
                $h = imagesy($src);
                $canvas = imagecreatetruecolor($w, $h);
                $white  = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
                imagealphablending($canvas, true);   // Quelle wird auf Weiß geblendet
                imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
                imagesavealpha($canvas, false);      // ohne Alpha ausgeben -> keine SMask
                ob_start();
                imagepng($canvas);
                $flat = ob_get_clean();
                imagedestroy($src);
                imagedestroy($canvas);
                if (is_string($flat) && $flat !== '') {
                    return 'data:image/png;base64,' . base64_encode($flat);
                }
            }
        }
        $info = @getimagesizefromstring($data);
        $mime = is_array($info) && !empty($info['mime']) ? $info['mime'] : 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

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
     * Rendert die Rechnung als PDF/A-3 und bettet das ZUGFeRD-/Factur-X-XML als
     * zugehörige Datei ein (hybride E-Rechnung). Gibt die PDF-Bytes zurück.
     *
     * @param array<string,mixed> $data
     */
    public static function renderZugferd(string $template, array $data, string $xml): string
    {
        $html = View::render($template, $data, layout: null);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', dirname(__DIR__, 2));
        $options->setIsPdfAEnabled(true); // PDF/A-3 für ZUGFeRD

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // XML in eine temporäre Datei schreiben (Cpdf liest sie beim Einbetten).
        $tmp = tempnam(sys_get_temp_dir(), 'fx_') ?: (sys_get_temp_dir() . '/factur-x-' . getmypid() . '.xml');
        file_put_contents($tmp, $xml);

        try {
            $cpdf = $dompdf->getCanvas()->get_cpdf();
            $cpdf->addEmbeddedFile(
                $tmp,
                ZugferdService::FILENAME,
                'Rechnung (Factur-X / ZUGFeRD EN 16931)',
                'text/xml',
                [$cpdf->catalogId => 'Alternative']
            );
            $cpdf->setAdditionalXmpRdf(ZugferdService::xmpExtension());
            return (string) $dompdf->output();
        } finally {
            @unlink($tmp);
        }
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
