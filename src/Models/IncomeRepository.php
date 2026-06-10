<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Core\Format;

final class IncomeRepository extends BaseRepository
{
    protected string $table = 'income';

    /** @return array<int,array<string,mixed>> */
    public function search(string $term = '', ?int $year = null): array
    {
        $where  = [];
        $params = [];
        if ($term !== '') {
            $where[]     = '(i.source LIKE :t OR i.category LIKE :t OR i.note LIKE :t)';
            $params['t'] = '%' . $term . '%';
        }
        if ($year !== null) {
            $where[]     = "strftime('%Y', i.income_date) = :y";
            $params['y'] = (string) $year;
        }
        $sql = 'SELECT i.*, p.name AS project_name FROM income i LEFT JOIN project p ON p.id = i.project_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY i.income_date DESC, i.id DESC';
        return $this->db()->fetchAll($sql, $params);
    }

    /** @return array<string,mixed>|null */
    public function findWithProject(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT i.*, p.name AS project_name FROM income i LEFT JOIN project p ON p.id = i.project_id WHERE i.id = :id',
            ['id' => $id]
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

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function fillable(array $data): array
    {
        return [
            'income_date' => ((string) ($data['income_date'] ?? '')) ?: date('Y-m-d'),
            'source'      => (string) ($data['source'] ?? ''),
            'category'    => ((string) ($data['category'] ?? '')) ?: 'Sonstige Einnahmen',
            'project_id'  => ((int) ($data['project_id'] ?? 0)) ?: null,
            'amount_cents' => Format::toCents((string) ($data['amount'] ?? '0')),
            'note'        => (string) ($data['note'] ?? ''),
        ];
    }

    /** Gängige EÜR-Einnahmenkategorien für das Dropdown. */
    public static function categories(): array
    {
        return [
            'Affiliate', 'Werbeeinnahmen', 'Provisionen', 'Produktverkauf',
            'Dienstleistung', 'Zinsen', 'Sonstige Einnahmen',
        ];
    }
}
