<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Core\Format;

final class ExpenseRepository extends BaseRepository
{
    protected string $table = 'expense';

    /** @return array<int,array<string,mixed>> */
    public function search(string $term = '', ?int $year = null): array
    {
        $where  = [];
        $params = [];
        if ($term !== '') {
            $where[]     = '(supplier LIKE :t OR category LIKE :t OR tax_category LIKE :t OR note LIKE :t)';
            $params['t'] = '%' . $term . '%';
        }
        if ($year !== null) {
            $where[]     = "strftime('%Y', expense_date) = :y";
            $params['y'] = (string) $year;
        }
        $sql = 'SELECT * FROM expense';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY expense_date DESC, id DESC';
        return $this->db()->fetchAll($sql, $params);
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

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function fillable(array $data): array
    {
        $status = (string) ($data['status'] ?? 'paid');
        if (!in_array($status, ['open', 'paid'], true)) {
            $status = 'paid';
        }
        return [
            'expense_date' => ((string) ($data['expense_date'] ?? '')) ?: date('Y-m-d'),
            'supplier'     => (string) ($data['supplier'] ?? ''),
            'category'     => (string) ($data['category'] ?? ''),
            'tax_category' => (string) ($data['tax_category'] ?? ''),
            'amount_cents' => Format::toCents((string) ($data['amount'] ?? '0')),
            'vat_rate'     => (int) ($data['vat_rate'] ?? 0),
            'method'       => (string) ($data['method'] ?? ''),
            'status'       => $status,
            'note'         => (string) ($data['note'] ?? ''),
        ];
    }

    /** Gängige EÜR-Ausgabenkategorien für das Dropdown. */
    public static function taxCategories(): array
    {
        return [
            'Wareneinkauf', 'Bürobedarf', 'Software/Lizenzen', 'Hardware/GWG',
            'Telekommunikation', 'Reisekosten', 'Fahrtkosten/Kfz', 'Fortbildung',
            'Miete/Raumkosten', 'Versicherungen/Beiträge', 'Werbung/Marketing',
            'Fremdleistungen', 'Bankgebühren', 'Porto/Versand', 'Sonstiges',
        ];
    }
}
