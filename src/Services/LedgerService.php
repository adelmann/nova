<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\Auth;
use Nova\Core\DB;

/**
 * Schreibt Einträge ins Buchungsjournal (append-only). Jede Einnahme und jede
 * Ausgabe erzeugt genau einen Journal-Eintrag. Korrekturen erfolgen über
 * Gegenbuchungen, nicht durch Änderung/Löschung (per DB-Trigger erzwungen).
 */
final class LedgerService
{
    /**
     * Verbucht eine Einnahme (positiver Betrag).
     */
    public static function recordIncome(
        string $date,
        int $amountCents,
        string $referenceType,
        ?int $referenceId,
        string $category,
        string $description,
        ?int $receiptId = null
    ): int {
        return self::insert('income', $date, $amountCents, $referenceType, $referenceId, $category, $description, $receiptId);
    }

    /**
     * Verbucht eine Ausgabe (wird intern negativ gespeichert).
     */
    public static function recordExpense(
        string $date,
        int $amountCents,
        string $referenceType,
        ?int $referenceId,
        string $category,
        string $description,
        ?int $receiptId = null
    ): int {
        return self::insert('expense', $date, -abs($amountCents), $referenceType, $referenceId, $category, $description, $receiptId);
    }

    /**
     * Hält das Journal mit dem aktuellen Stand einer Ausgabe synchron, ohne
     * bestehende Einträge zu ändern: bucht nur die Differenz zwischen Soll
     * (−Betrag, falls bezahlt; 0 falls offen) und der bisher gebuchten Summe.
     * Dadurch bleibt das Journal append-only (GoBD) und die EÜR stimmt trotzdem.
     */
    /**
     * Hält das Journal mit dem aktuellen Stand einer direkten Einnahme synchron
     * (analog zu syncExpense, aber positiv). Bucht nur die Differenz, damit das
     * Journal append-only bleibt (GoBD).
     */
    public static function syncIncome(
        int $incomeId,
        int $targetAmountCents,
        string $date,
        string $category,
        string $description,
        ?int $receiptId = null
    ): void {
        $db      = DB::getInstance();
        $current = (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM ledger_entry
             WHERE reference_type = 'income_direct' AND reference_id = :id",
            ['id' => $incomeId]
        );
        $diff = $targetAmountCents - $current;
        if ($diff === 0) {
            return;
        }
        self::insert('income', $date, $diff, 'income_direct', $incomeId, $category, $description, $receiptId);
    }

    public static function syncExpense(
        int $expenseId,
        int $targetAmountCents,
        string $date,
        string $category,
        string $description,
        ?int $receiptId = null
    ): void {
        $db      = DB::getInstance();
        $current = (int) $db->fetchColumn(
            "SELECT COALESCE(SUM(amount_cents), 0) FROM ledger_entry
             WHERE reference_type = 'expense' AND reference_id = :id",
            ['id' => $expenseId]
        );
        $diff = $targetAmountCents - $current;
        if ($diff === 0) {
            return;
        }
        self::insert('expense', $date, $diff, 'expense', $expenseId, $category, $description, $receiptId);
    }

    private static function insert(
        string $type,
        string $date,
        int $amountCents,
        string $referenceType,
        ?int $referenceId,
        string $category,
        string $description,
        ?int $receiptId
    ): int {
        $db = DB::getInstance();
        $db->query(
            'INSERT INTO ledger_entry (entry_date, type, reference_type, reference_id, category, description, amount_cents, receipt_id, created_by)
             VALUES (:d, :t, :rt, :rid, :cat, :desc, :amt, :receipt, :by)',
            [
                'd' => $date, 't' => $type, 'rt' => $referenceType, 'rid' => $referenceId,
                'cat' => $category, 'desc' => $description, 'amt' => $amountCents,
                'receipt' => $receiptId, 'by' => Auth::id(),
            ]
        );
        return $db->lastInsertId();
    }
}
