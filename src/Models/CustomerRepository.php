<?php

declare(strict_types=1);

namespace Nova\Models;

final class CustomerRepository extends BaseRepository
{
    protected string $table = 'customer';

    /**
     * Liste mit optionaler Volltextsuche über Name/Firma/E-Mail/Ort.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $term = '', bool $includeArchived = false): array
    {
        $where  = [];
        $params = [];
        if (!$includeArchived) {
            $where[] = 'archived_at IS NULL';
        }
        if ($term !== '') {
            $where[]     = '(company_name LIKE :t OR contact_name LIKE :t OR email LIKE :t OR city LIKE :t)';
            $params['t'] = '%' . $term . '%';
        }
        $sql = 'SELECT * FROM customer';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY company_name, contact_name';
        return $this->db()->fetchAll($sql, $params);
    }

    /**
     * Aktive (nicht archivierte) Kunden für Auswahlfelder. Optional wird ein
     * bestimmter Kunde immer eingeschlossen (z.B. der bereits zugeordnete beim
     * Bearbeiten), auch wenn er archiviert ist.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forSelect(?int $includeId = null): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM customer WHERE archived_at IS NULL OR id = :inc ORDER BY company_name, contact_name',
            ['inc' => $includeId ?? 0]
        );
    }

    public function archive(int $id): void
    {
        $this->updateById($id, ['archived_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function unarchive(int $id): void
    {
        $this->updateById($id, ['archived_at' => null, 'updated_at' => date('Y-m-d H:i:s')]);
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
     * Whitelist der beschreibbaren Felder.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function fillable(array $data): array
    {
        $keys = [
            'company_name', 'contact_name', 'address_line1', 'address_line2',
            'zip', 'city', 'country', 'email', 'phone', 'vat_id', 'type', 'notes',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = (string) ($data[$k] ?? '');
        }
        if (!in_array($out['type'], ['business', 'private'], true)) {
            $out['type'] = 'business';
        }
        return $out;
    }

    public function countProjects(int $customerId): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM project WHERE customer_id = :id',
            ['id' => $customerId]
        );
    }

    /** Zählt alle Datensätze, die ein Löschen des Kunden per FK verhindern. */
    public function countReferences(int $customerId): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT (SELECT COUNT(*) FROM project  WHERE customer_id = :id)
                  + (SELECT COUNT(*) FROM invoice  WHERE customer_id = :id)
                  + (SELECT COUNT(*) FROM quote    WHERE customer_id = :id)',
            ['id' => $customerId]
        );
    }
}
