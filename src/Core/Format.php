<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Formatierungshelfer für Geldbeträge (in Cent) und Datumswerte.
 */
final class Format
{
    /** Cent-Betrag als deutschen Eurobetrag formatieren, z.B. 9500 => "95,00 €". */
    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }

    /** "95,00" aus Cent (für Formularfelder, ohne Währungssymbol). */
    public static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /** Deutsche Eingabe ("1.234,56" oder "95,00" oder "95") in Cent umrechnen. */
    public static function toCents(string $input): int
    {
        $input = trim($input);
        if ($input === '') {
            return 0;
        }
        $normalized = str_replace(['.', ' '], '', $input); // Tausenderpunkte raus
        $normalized = str_replace(',', '.', $normalized);   // Dezimalkomma -> Punkt
        return (int) round(((float) $normalized) * 100);
    }

    /** ISO-Datum (Y-m-d) als deutsches Datum (d.m.Y). */
    public static function date(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }
        $ts = strtotime($iso);
        return $ts === false ? $iso : date('d.m.Y', $ts);
    }
}
