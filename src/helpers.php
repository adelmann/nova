<?php

declare(strict_types=1);

/**
 * Globale Helfer-Funktionen für die Views (laufen im globalen Namespace).
 */

use Nova\Core\Auth;
use Nova\Core\Csrf;
use Nova\Core\Format;
use Nova\Core\Session;
use Nova\Core\View;

/** HTML-escapen. */
function e(mixed $value): string
{
    return View::e($value);
}

/** Cent-Betrag als Eurobetrag. */
function money(int $cents): string
{
    return Format::money($cents);
}

/** Cent-Betrag ohne Währungssymbol (für Formularfelder). */
function amount(int $cents): string
{
    return Format::amount($cents);
}

/** ISO-Datum deutsch formatieren. */
function dt(?string $iso): string
{
    return Format::date($iso);
}

/** Verstecktes CSRF-Feld. */
function csrf_field(): string
{
    return Csrf::field();
}

/** Aktuell angemeldeter Benutzer (oder null). */
function current_user(): ?array
{
    return Auth::user();
}

/** Darf der angemeldete Benutzer die Capability? (für Views) */
function can(?string $cap): bool
{
    return Auth::can($cap);
}

/** Hilfsfunktion für aktive Navigation. */
function nav_active(string $path, string $current): string
{
    if ($path === '/') {
        return $current === '/' ? 'active' : '';
    }
    return str_starts_with($current, $path) ? 'active' : '';
}

/** Flash-Messages abholen. */
function flash_messages(): array
{
    return Session::takeFlash();
}

/** Ein Partial rendern und zurückgeben. */
function partial(string $template, array $data = []): string
{
    return View::renderPartial($template, $data);
}

/**
 * Löst eine Select-Auswahl mit „Andere…"-Option auf: Bei `__custom__` wird der
 * Wert des zugehörigen Freitextfeldes (`<name>_custom`) genommen.
 */
function pick_value(string $selected, string $custom): string
{
    return $selected === '__custom__' ? trim($custom) : $selected;
}
