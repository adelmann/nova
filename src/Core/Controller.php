<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Basisklasse für Controller mit gemeinsamen Helfern (Rendering, Redirects,
 * CSRF-Prüfung).
 */
abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $to): never
    {
        Response::redirect($to);
    }

    protected function back(string $fallback = '/'): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? $fallback;
        Response::redirect($ref);
    }

    /**
     * Erzwingt eine gültige CSRF-Prüfung bei POST-Requests.
     */
    protected function verifyCsrf(Request $request): void
    {
        if (!$request->isPost()) {
            return;
        }
        $token = $request->str('_csrf');
        if (!Csrf::verify($token)) {
            Session::flash('error', 'Sicherheitsprüfung fehlgeschlagen. Bitte erneut versuchen.');
            Response::redirect($request->path);
        }
    }
}
