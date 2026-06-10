<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Core\Format;

final class CatalogItemRepository extends BaseRepository
{
    protected string $table = 'catalog_item';

    /** @return array<int,array<string,mixed>> */
    public function all(string $orderBy = 'name'): array
    {
        return $this->db()->fetchAll("SELECT * FROM catalog_item ORDER BY {$orderBy}");
    }

    /** Aktive (nicht archivierte) Einträge für die Auswahl in Formularen. @return array<int,array<string,mixed>> */
    public function active(): array
    {
        return $this->db()->fetchAll('SELECT * FROM catalog_item WHERE archived_at IS NULL ORDER BY name');
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

    public function archive(int $id): void
    {
        $this->updateById($id, ['archived_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function unarchive(int $id): void
    {
        $this->updateById($id, ['archived_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function fillable(array $data): array
    {
        return [
            'name'             => trim((string) ($data['name'] ?? '')),
            'unit'             => ((string) ($data['unit'] ?? 'Stk')) ?: 'Stk',
            'unit_price_cents' => Format::toCents((string) ($data['unit_price'] ?? '0')),
        ];
    }
}
