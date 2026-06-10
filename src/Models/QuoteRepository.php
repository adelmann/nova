<?php

declare(strict_types=1);

namespace Nova\Models;

final class QuoteRepository extends BaseRepository
{
    protected string $table = 'quote';

    /** @return array<int,array<string,mixed>> */
    public function allWithCustomer(): array
    {
        return $this->db()->fetchAll(
            'SELECT q.*, c.company_name, c.contact_name
             FROM quote q JOIN customer c ON c.id = q.customer_id
             ORDER BY q.created_at DESC'
        );
    }

    /** @return array<string,mixed>|null */
    public function findWithCustomer(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT q.*, c.company_name, c.contact_name, c.address_line1, c.address_line2,
                    c.zip, c.city, c.country, c.vat_id AS customer_vat_id
             FROM quote q JOIN customer c ON c.id = q.customer_id
             WHERE q.id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function items(int $quoteId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM quote_item WHERE quote_id = :id ORDER BY position',
            ['id' => $quoteId]
        );
    }

    /**
     * Legt ein Angebot inkl. Positionen an (Status draft, ohne Nummer).
     *
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
     * Aktualisiert ein Angebot inkl. Positionen (nur im Entwurf erlaubt).
     *
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

    /** @param array<int,array<string,mixed>> $items */
    private function replaceItems(int $quoteId, array $items): void
    {
        $this->db()->query('DELETE FROM quote_item WHERE quote_id = :id', ['id' => $quoteId]);
        foreach ($items as $item) {
            $this->db()->query(
                'INSERT INTO quote_item (quote_id, position, description, quantity, unit, unit_price_cents, line_total_cents)
                 VALUES (:q, :pos, :desc, :qty, :unit, :price, :total)',
                [
                    'q'     => $quoteId,
                    'pos'   => $item['position'],
                    'desc'  => $item['description'],
                    'qty'   => $item['quantity'],
                    'unit'  => $item['unit'],
                    'price' => $item['unit_price_cents'],
                    'total' => $item['line_total_cents'],
                ]
            );
        }
    }

    public function setStatus(int $id, string $status): void
    {
        $this->updateById($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function setNumber(int $id, string $number): void
    {
        $this->updateById($id, ['number' => $number]);
    }

    public function setConvertedInvoice(int $id, int $invoiceId): void
    {
        $this->updateById($id, ['converted_invoice_id' => $invoiceId, 'status' => 'accepted']);
    }
}
