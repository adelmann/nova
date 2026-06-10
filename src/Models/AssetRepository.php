<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Core\Format;

final class AssetRepository extends BaseRepository
{
    protected string $table = 'asset';

    /** @return array<int,array<string,mixed>> */
    public function allOrdered(): array
    {
        return $this->db()->fetchAll('SELECT * FROM asset ORDER BY acquired_date DESC, id DESC');
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
        $method = (string) ($data['method'] ?? 'linear');
        if (!in_array($method, ['linear', 'gwg'], true)) {
            $method = 'linear';
        }
        return [
            'name'              => (string) ($data['name'] ?? ''),
            'supplier'          => (string) ($data['supplier'] ?? ''),
            'acquired_date'     => ((string) ($data['acquired_date'] ?? '')) ?: date('Y-m-d'),
            'cost_cents'        => Format::toCents((string) ($data['cost'] ?? '0')),
            'useful_life_years' => max(1, (int) ($data['useful_life_years'] ?? 1)),
            'method'            => $method,
            'note'              => (string) ($data['note'] ?? ''),
        ];
    }

    /** Übliche Nutzungsdauern (AfA-Tabelle, Auswahl) für das Eingabefeld. */
    public static function usefulLifeSuggestions(): array
    {
        return [
            'Computer/Notebook/Tablet' => 3,
            'Smartphone'               => 5,
            'Drucker/Peripherie'       => 3,
            'Büromöbel'                => 13,
            'Maschinen (allgemein)'    => 8,
            'Pkw'                      => 6,
            'Werkzeuge'                => 5,
        ];
    }
}
