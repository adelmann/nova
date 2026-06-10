<?php

declare(strict_types=1);

namespace Nova\Models;

final class ReminderRepository extends BaseRepository
{
    protected string $table = 'reminder';

    /** @return array<int,array<string,mixed>> */
    public function allWithInvoice(): array
    {
        return $this->db()->fetchAll(
            'SELECT r.*, i.number AS invoice_number, c.company_name, c.contact_name
             FROM reminder r
             JOIN invoice i ON i.id = r.invoice_id
             JOIN customer c ON c.id = i.customer_id
             ORDER BY r.created_at DESC'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forInvoice(int $invoiceId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM reminder WHERE invoice_id = :id ORDER BY level',
            ['id' => $invoiceId]
        );
    }

    public function highestLevel(int $invoiceId): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COALESCE(MAX(level), 0) FROM reminder WHERE invoice_id = :id',
            ['id' => $invoiceId]
        );
    }

    /** @param array<string,mixed> $data */
    public function createReminder(array $data): int
    {
        return $this->insert($data);
    }

    public function setPdfPath(int $id, string $path): void
    {
        $this->updateById($id, ['pdf_path' => $path]);
    }
}
