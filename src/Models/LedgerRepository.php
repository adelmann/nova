<?php

declare(strict_types=1);

namespace Nova\Models;

final class LedgerRepository extends BaseRepository
{
    protected string $table = 'ledger_entry';

    /** @return array<int,array<string,mixed>> */
    public function forYear(int $year): array
    {
        return $this->db()->fetchAll(
            "SELECT le.*, u.email AS user_email
             FROM ledger_entry le
             LEFT JOIN user u ON u.id = le.created_by
             WHERE strftime('%Y', le.entry_date) = :y
             ORDER BY le.entry_date, le.id",
            ['y' => (string) $year]
        );
    }

    /** @return array<int,int> Liste der Jahre mit Buchungen. */
    public function years(): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT DISTINCT strftime('%Y', entry_date) AS y FROM ledger_entry ORDER BY y DESC"
        );
        $years = array_map(static fn ($r): int => (int) $r['y'], $rows);
        if ($years === []) {
            $years[] = (int) date('Y');
        }
        return $years;
    }
}
