<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\DB;

/**
 * Einnahmen-Überschuss-Rechnung: Aggregationen über das Buchungsjournal.
 * Alle Beträge in Cent. Einnahmen positiv, Ausgaben negativ gespeichert.
 */
final class EuerService
{
    /** @return array{income:int,expense:int,profit:int} */
    public static function summary(int $year): array
    {
        $income = (int) self::col(
            "SELECT COALESCE(SUM(amount_cents),0) FROM ledger_entry WHERE type='income' AND strftime('%Y',entry_date)=:y",
            $year
        );
        $expense = (int) self::col(
            "SELECT COALESCE(SUM(amount_cents),0) FROM ledger_entry WHERE type='expense' AND strftime('%Y',entry_date)=:y",
            $year
        );
        // expense ist negativ gespeichert.
        return ['income' => $income, 'expense' => -$expense, 'profit' => $income + $expense];
    }

    /**
     * Monatsübersicht 1..12.
     *
     * @return array<int,array{income:int,expense:int}>
     */
    public static function byMonth(int $year): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = ['income' => 0, 'expense' => 0];
        }
        $rows = DB::getInstance()->fetchAll(
            "SELECT CAST(strftime('%m',entry_date) AS INTEGER) m, type, SUM(amount_cents) s
             FROM ledger_entry WHERE strftime('%Y',entry_date)=:y GROUP BY m, type",
            ['y' => (string) $year]
        );
        foreach ($rows as $r) {
            $m = (int) $r['m'];
            if ($r['type'] === 'income') {
                $months[$m]['income'] = (int) $r['s'];
            } else {
                $months[$m]['expense'] = -(int) $r['s'];
            }
        }
        return $months;
    }

    /**
     * Kategorienübersicht getrennt nach Einnahmen und Ausgaben.
     *
     * @return array{income:array<string,int>,expense:array<string,int>}
     */
    public static function byCategory(int $year): array
    {
        $rows = DB::getInstance()->fetchAll(
            "SELECT type, COALESCE(NULLIF(category,''),'(ohne Kategorie)') cat, SUM(amount_cents) s
             FROM ledger_entry WHERE strftime('%Y',entry_date)=:y GROUP BY type, cat ORDER BY s",
            ['y' => (string) $year]
        );
        $out = ['income' => [], 'expense' => []];
        foreach ($rows as $r) {
            if ($r['type'] === 'income') {
                $out['income'][$r['cat']] = (int) $r['s'];
            } else {
                $out['expense'][$r['cat']] = -(int) $r['s'];
            }
        }
        return $out;
    }

    /**
     * Alle Einzelbuchungen des Jahres (chronologisch je Typ) für die
     * Detailaufstellung / den Kontennachweis.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function entries(int $year): array
    {
        return DB::getInstance()->fetchAll(
            "SELECT entry_date, type, COALESCE(NULLIF(category,''),'(ohne Kategorie)') AS category,
                    description, amount_cents
             FROM ledger_entry
             WHERE strftime('%Y',entry_date)=:y
             ORDER BY type DESC, entry_date, id",
            ['y' => (string) $year]
        );
    }

    /** @return array<int,int> Jahre mit Buchungen. */
    public static function years(): array
    {
        $rows = DB::getInstance()->fetchAll(
            "SELECT DISTINCT strftime('%Y',entry_date) y FROM ledger_entry ORDER BY y DESC"
        );
        $years = array_map(static fn ($r): int => (int) $r['y'], $rows);
        return $years === [] ? [(int) date('Y')] : $years;
    }

    private static function col(string $sql, int $year): mixed
    {
        return DB::getInstance()->fetchColumn($sql, ['y' => (string) $year]);
    }
}
