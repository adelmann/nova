<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Core\Format;

final class RecurringExpenseRepository extends BaseRepository
{
    protected string $table = 'recurring_expense';

    /** @return array<int,array<string,mixed>> */
    public function allOrdered(): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM recurring_expense ORDER BY active DESC, next_date'
        );
    }

    /** Fällige, aktive Profile (next_date <= heute). @return array<int,array<string,mixed>> */
    public function due(): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM recurring_expense WHERE active = 1 AND next_date <= :today ORDER BY next_date',
            ['today' => date('Y-m-d')]
        );
    }

    /** @param array<string,mixed> $data */
    public function createFromInput(array $data): int
    {
        return $this->insert($this->fillable($data));
    }

    /** @param array<string,mixed> $data */
    public function updateFromInput(int $id, array $data): void
    {
        $payload = $this->fillable($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');
        $this->updateById($id, $payload);
    }

    /** Setzt das nächste Fälligkeitsdatum und vermerkt den Lauf. */
    public function advance(int $id, string $nextDate): void
    {
        $this->updateById($id, [
            'next_date'  => $nextDate,
            'last_run'   => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function fillable(array $data): array
    {
        $unit = (string) ($data['interval_unit'] ?? 'month');
        if (!in_array($unit, ['month', 'quarter', 'year'], true)) {
            $unit = 'month';
        }
        return [
            'title'         => (string) ($data['title'] ?? ''),
            'supplier'      => (string) ($data['supplier'] ?? ''),
            'category'      => (string) ($data['category'] ?? ''),
            'tax_category'  => (string) ($data['tax_category'] ?? ''),
            'amount_cents'  => Format::toCents((string) ($data['amount'] ?? '0')),
            'vat_rate'      => (int) ($data['vat_rate'] ?? 0),
            'method'        => (string) ($data['method'] ?? ''),
            'interval_unit' => $unit,
            'next_date'     => ((string) ($data['next_date'] ?? '')) ?: date('Y-m-d'),
            'note'          => (string) ($data['note'] ?? ''),
            'active'        => isset($data['active']) && (int) $data['active'] === 1 ? 1 : 0,
        ];
    }
}
