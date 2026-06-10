<?php

declare(strict_types=1);

namespace Nova\Models;

final class RecurringInvoiceRepository extends BaseRepository
{
    protected string $table = 'recurring_invoice';

    /** @return array<int,array<string,mixed>> */
    public function allWithCustomer(): array
    {
        return $this->db()->fetchAll(
            'SELECT r.*, c.company_name, c.contact_name
             FROM recurring_invoice r JOIN customer c ON c.id = r.customer_id
             ORDER BY r.active DESC, r.next_date'
        );
    }

    /** @return array<string,mixed>|null */
    public function findWithCustomer(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT r.*, c.company_name, c.contact_name
             FROM recurring_invoice r JOIN customer c ON c.id = r.customer_id WHERE r.id = :id',
            ['id' => $id]
        );
    }

    /** Fällige, aktive Profile (next_date <= heute). @return array<int,array<string,mixed>> */
    public function due(): array
    {
        return $this->db()->fetchAll(
            "SELECT * FROM recurring_invoice WHERE active = 1 AND next_date <= :today ORDER BY next_date",
            ['today' => date('Y-m-d')]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function items(int $recurringId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM recurring_invoice_item WHERE recurring_id = :id ORDER BY position',
            ['id' => $recurringId]
        );
    }

    /**
     * @param array<string,mixed> $header
     * @param array<int,array<string,mixed>> $items
     */
    public function createWithItems(array $header, array $items): int
    {
        return $this->db()->transaction(function () use ($header, $items): int {
            $id = $this->insert($header);
            $this->replaceItems($id, $items);
            return $id;
        });
    }

    /**
     * @param array<string,mixed> $header
     * @param array<int,array<string,mixed>> $items
     */
    public function updateWithItems(int $id, array $header, array $items): void
    {
        $this->db()->transaction(function () use ($id, $header, $items): void {
            $header['updated_at'] = date('Y-m-d H:i:s');
            $this->updateById($id, $header);
            $this->replaceItems($id, $items);
        });
    }

    /** Setzt das nächste Fälligkeitsdatum und vermerkt den Lauf. */
    public function advance(int $id, string $nextDate): void
    {
        $this->updateById($id, ['next_date' => $nextDate, 'last_run' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function replaceItems(int $id, array $items): void
    {
        $this->db()->query('DELETE FROM recurring_invoice_item WHERE recurring_id = :id', ['id' => $id]);
        foreach ($items as $item) {
            $this->db()->query(
                'INSERT INTO recurring_invoice_item (recurring_id, position, description, quantity, unit, unit_price_cents)
                 VALUES (:r, :pos, :desc, :qty, :unit, :price)',
                [
                    'r' => $id, 'pos' => $item['position'], 'desc' => $item['description'],
                    'qty' => $item['quantity'], 'unit' => $item['unit'], 'price' => $item['unit_price_cents'],
                ]
            );
        }
    }
}
