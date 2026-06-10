<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Einfache PHP-Template-Engine. Templates liegen unter views/ und werden in
 * das Layout views/layout.php eingebettet (sofern nicht $layout = null).
 */
final class View
{
    private static string $viewsPath = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    /**
     * Rendert ein Template und gibt das Ergebnis als String zurück.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::renderPartial($template, $data);

        if ($layout === null) {
            return $content;
        }

        return self::renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string,mixed> $data */
    public static function renderPartial(string $template, array $data = []): string
    {
        $file = self::$viewsPath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View nicht gefunden: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /** HTML-Escaping-Helfer. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
