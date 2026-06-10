<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\DB;

/**
 * Vergibt lückenlose, fortlaufende Nummern je Scope (invoice|quote) und Jahr.
 * Die Vergabe läuft in einer IMMEDIATE-Transaktion, damit zwei gleichzeitige
 * Anfragen nie dieselbe Nummer erhalten (§14 UStG: lückenlos, eindeutig).
 */
final class NumberSequenceService
{
    /**
     * Reserviert die nächste Nummer und gibt sie formatiert zurück.
     *
     * @param string $format Muster mit {YYYY}, {YY}, {####} (Anzahl # = Stellen)
     */
    public static function next(string $scope, string $format, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $db = DB::getInstance();
        $pdo = $db->pdo();

        // Exklusive Schreibsperre für den kritischen Abschnitt.
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $db->query(
                'INSERT INTO number_sequence (scope, year, last_value) VALUES (:s, :y, 0)
                 ON CONFLICT(scope, year) DO NOTHING',
                ['s' => $scope, 'y' => $year]
            );
            $db->query(
                'UPDATE number_sequence SET last_value = last_value + 1
                 WHERE scope = :s AND year = :y',
                ['s' => $scope, 'y' => $year]
            );
            $value = (int) $db->fetchColumn(
                'SELECT last_value FROM number_sequence WHERE scope = :s AND year = :y',
                ['s' => $scope, 'y' => $year]
            );
            $pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }

        return self::format($format, $year, $value);
    }

    public static function format(string $format, int $year, int $value): string
    {
        $out = str_replace(
            ['{YYYY}', '{YY}'],
            [(string) $year, substr((string) $year, -2)],
            $format
        );

        // {####} -> Nummer mit so vielen Stellen wie # im Platzhalter.
        $out = preg_replace_callback('/\{(#+)\}/', static function (array $m) use ($value): string {
            return str_pad((string) $value, strlen($m[1]), '0', STR_PAD_LEFT);
        }, $out);

        return (string) $out;
    }
}
