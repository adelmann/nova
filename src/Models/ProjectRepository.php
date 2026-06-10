<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Core\Format;

final class ProjectRepository extends BaseRepository
{
    protected string $table = 'project';

    /** @return array<int,array<string,mixed>> */
    public function allWithCustomer(): array
    {
        return $this->db()->fetchAll(
            'SELECT p.*, c.company_name, c.contact_name
             FROM project p
             LEFT JOIN customer c ON c.id = p.customer_id
             ORDER BY p.created_at DESC'
        );
    }

    /** @return array<string,mixed>|null */
    public function findWithCustomer(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT p.*, c.company_name, c.contact_name
             FROM project p
             LEFT JOIN customer c ON c.id = p.customer_id
             WHERE p.id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forCustomer(int $customerId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM project WHERE customer_id = :id ORDER BY created_at DESC',
            ['id' => $customerId]
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
        $status = (string) ($data['status'] ?? 'active');
        if (!in_array($status, ['active', 'paused', 'done', 'cancelled'], true)) {
            $status = 'active';
        }
        $type = (string) ($data['project_type'] ?? 'customer');
        if (!in_array($type, ['customer', 'internal'], true)) {
            $type = 'customer';
        }
        // Interne Projekte haben keinen Kunden.
        $customerId = $type === 'internal' ? null : ((int) ($data['customer_id'] ?? 0) ?: null);

        return [
            'customer_id'       => $customerId,
            'project_type'      => $type,
            'name'              => (string) ($data['name'] ?? ''),
            'status'            => $status,
            'hourly_rate_cents' => Format::toCents((string) ($data['hourly_rate'] ?? '0')),
            'description'       => (string) ($data['description'] ?? ''),
            'start_date'        => ((string) ($data['start_date'] ?? '')) ?: null,
            'end_date'          => ((string) ($data['end_date'] ?? '')) ?: null,
        ];
    }
}
