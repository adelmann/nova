<?php

declare(strict_types=1);

namespace Nova\Models;

final class VendorRepository extends BaseRepository
{
    protected string $table = 'vendor';

    /** @return array<int,array<string,mixed>> */
    public function search(string $term = '', bool $includeArchived = false): array
    {
        $where  = [];
        $params = [];
        if (!$includeArchived) {
            $where[] = 'archived_at IS NULL';
        }
        if ($term !== '') {
            $where[]     = '(name LIKE :t OR contact_name LIKE :t OR email LIKE :t OR city LIKE :t)';
            $params['t'] = '%' . $term . '%';
        }
        $sql = 'SELECT * FROM vendor';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY name';
        return $this->db()->fetchAll($sql, $params);
    }

    /** Nicht archivierte Namen für Auswahlfelder. @return array<int,string> */
    public function names(): array
    {
        $rows = $this->db()->fetchAll('SELECT name FROM vendor WHERE archived_at IS NULL ORDER BY name');
        return array_map(static fn ($r): string => (string) $r['name'], $rows);
    }

    /** @return array<string,mixed>|null */
    public function findByName(string $name): ?array
    {
        return $this->db()->fetch('SELECT * FROM vendor WHERE name = :n COLLATE NOCASE', ['n' => $name]);
    }

    /** Legt einen Lieferanten nur mit Namen an, falls noch nicht vorhanden. */
    public function ensure(string $name): void
    {
        $name = trim($name);
        if ($name === '' || $this->findByName($name) !== null) {
            return;
        }
        $this->insert(['name' => $name]);
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
            'name'          => trim((string) ($data['name'] ?? '')),
            'contact_name'  => (string) ($data['contact_name'] ?? ''),
            'email'         => (string) ($data['email'] ?? ''),
            'phone'         => (string) ($data['phone'] ?? ''),
            'website'       => (string) ($data['website'] ?? ''),
            'address_line1' => (string) ($data['address_line1'] ?? ''),
            'zip'           => (string) ($data['zip'] ?? ''),
            'city'          => (string) ($data['city'] ?? ''),
            'vat_id'        => (string) ($data['vat_id'] ?? ''),
            'note'          => (string) ($data['note'] ?? ''),
        ];
    }
}
