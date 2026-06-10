<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Services\NumberSequenceService;

final class InvoiceRepository extends BaseRepository
{
    protected string $table = 'invoice';

    /** @return array<int,array<string,mixed>> */
    public function allWithCustomer(): array
    {
        return $this->db()->fetchAll(
            'SELECT i.*, c.company_name, c.contact_name
             FROM invoice i JOIN customer c ON c.id = i.customer_id
             ORDER BY i.created_at DESC'
        );
    }

    /** @return array<string,mixed>|null */
    public function findWithCustomer(int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT i.*, c.company_name, c.contact_name, c.address_line1, c.address_line2,
                    c.zip, c.city, c.country, c.vat_id AS customer_vat_id
             FROM invoice i JOIN customer c ON c.id = i.customer_id
             WHERE i.id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function items(int $invoiceId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM invoice_item WHERE invoice_id = :id ORDER BY position',
            ['id' => $invoiceId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function payments(int $invoiceId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM payment WHERE invoice_id = :id ORDER BY paid_on',
            ['id' => $invoiceId]
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

    /**
     * Erzeugt einen Rechnungsentwurf aus einem Angebot.
     *
     * @param array<string,mixed> $quote
     * @param array<int,array<string,mixed>> $quoteItems
     */
    public function createFromQuote(array $quote, array $quoteItems): int
    {
        $header = [
            'customer_id'         => (int) $quote['customer_id'],
            'project_id'          => $quote['project_id'] !== null ? (int) $quote['project_id'] : null,
            'quote_id'            => (int) $quote['id'],
            'status'              => 'draft',
            'is_locked'           => 0,
            'invoice_date'        => date('Y-m-d'),
            'is_kleinunternehmer' => (int) $quote['is_kleinunternehmer'],
            'vat_rate'            => (int) $quote['vat_rate'],
            'intro_text'          => (string) $quote['intro_text'],
            'footer_text'         => (string) $quote['footer_text'],
            'net_total_cents'     => (int) $quote['net_total_cents'],
            'vat_total_cents'     => (int) $quote['vat_total_cents'],
            'gross_total_cents'   => (int) $quote['gross_total_cents'],
        ];
        $items = array_map(static fn (array $it): array => [
            'position'         => (int) $it['position'],
            'description'      => (string) $it['description'],
            'quantity'         => (float) $it['quantity'],
            'unit'             => (string) $it['unit'],
            'unit_price_cents' => (int) $it['unit_price_cents'],
            'vat_rate'         => (int) $quote['vat_rate'],
            'line_total_cents' => (int) $it['line_total_cents'],
        ], $quoteItems);

        return $this->createWithItems($header, $items);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function replaceItems(int $invoiceId, array $items): void
    {
        $this->db()->query('DELETE FROM invoice_item WHERE invoice_id = :id', ['id' => $invoiceId]);
        foreach ($items as $item) {
            $this->db()->query(
                'INSERT INTO invoice_item (invoice_id, position, description, quantity, unit, unit_price_cents, vat_rate, line_total_cents)
                 VALUES (:i, :pos, :desc, :qty, :unit, :price, :vat, :total)',
                [
                    'i'     => $invoiceId,
                    'pos'   => $item['position'],
                    'desc'  => $item['description'],
                    'qty'   => $item['quantity'],
                    'unit'  => $item['unit'],
                    'price' => $item['unit_price_cents'],
                    'vat'   => $item['vat_rate'] ?? 0,
                    'total' => $item['line_total_cents'],
                ]
            );
        }
    }

    /**
     * Finalisiert eine Rechnung: vergibt Nummer, setzt Fälligkeit, sperrt sie.
     * Gibt die vergebene Rechnungsnummer zurück.
     */
    public function finalize(int $id, string $numberFormat, int $paymentDays): string
    {
        $invoice = $this->find($id);
        if ($invoice === null) {
            throw new \RuntimeException('Rechnung nicht gefunden.');
        }
        if ((int) $invoice['is_locked'] === 1) {
            throw new \RuntimeException('Rechnung ist bereits finalisiert.');
        }

        $number  = NumberSequenceService::next('invoice', $numberFormat);
        $dueDate = date('Y-m-d', strtotime($invoice['invoice_date'] . ' +' . $paymentDays . ' days'));

        $this->updateById($id, [
            'number'       => $number,
            'status'       => 'sent',
            'is_locked'    => 1,
            'due_date'     => $dueDate,
            'finalized_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return $number;
    }

    /**
     * Erstellt eine Storno-Rechnung (negierte Positionen) zu einer finalisierten
     * Rechnung und gibt deren ID zurück.
     */
    public function createCancellation(int $originalId, string $numberFormat): int
    {
        $orig = $this->find($originalId);
        if ($orig === null) {
            throw new \RuntimeException('Rechnung nicht gefunden.');
        }

        // Nummer VOR der Transaktion vergeben – NumberSequenceService::next()
        // öffnet selbst eine IMMEDIATE-Transaktion (keine Verschachtelung).
        $number = NumberSequenceService::next('invoice', $numberFormat);

        return $this->db()->transaction(function () use ($orig, $originalId, $number): int {
            $header = [
                'number'              => $number,
                'customer_id'         => (int) $orig['customer_id'],
                'project_id'          => $orig['project_id'],
                'status'              => 'cancelled',
                'is_locked'           => 1,
                'invoice_date'        => date('Y-m-d'),
                'is_kleinunternehmer' => (int) $orig['is_kleinunternehmer'],
                'vat_rate'            => (int) $orig['vat_rate'],
                'intro_text'          => 'Storno zu Rechnung ' . $orig['number'],
                'footer_text'         => (string) $orig['footer_text'],
                'net_total_cents'     => -(int) $orig['net_total_cents'],
                'vat_total_cents'     => -(int) $orig['vat_total_cents'],
                'gross_total_cents'   => -(int) $orig['gross_total_cents'],
                'cancels_invoice_id'  => $originalId,
                'finalized_at'        => date('Y-m-d H:i:s'),
            ];
            $cancelId = $this->insert($header);

            foreach ($this->items($originalId) as $it) {
                $this->db()->query(
                    'INSERT INTO invoice_item (invoice_id, position, description, quantity, unit, unit_price_cents, vat_rate, line_total_cents)
                     VALUES (:i, :pos, :desc, :qty, :unit, :price, :vat, :total)',
                    [
                        'i' => $cancelId, 'pos' => $it['position'], 'desc' => $it['description'],
                        'qty' => $it['quantity'], 'unit' => $it['unit'],
                        'price' => -(int) $it['unit_price_cents'], 'vat' => $it['vat_rate'],
                        'total' => -(int) $it['line_total_cents'],
                    ]
                );
            }

            // Originalrechnung als storniert markieren (Status, nicht den Inhalt).
            $this->updateById($originalId, ['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
            return $cancelId;
        });
    }

    public function setArchivePath(int $id, string $relativePath): void
    {
        $this->updateById($id, ['pdf_archive_path' => $relativePath]);
    }

    /**
     * Offene (finalisierte, nicht stornierte, nicht voll bezahlte) Rechnungen
     * für den Zahlungsabgleich.
     *
     * @return array<int,array{id:int,number:string,open_cents:int}>
     */
    /** Stellt sicher, dass die Rechnung einen öffentlichen Bezahl-Token hat. */
    public function ensurePayToken(int $id): string
    {
        $inv = $this->find($id);
        $token = (string) ($inv['pay_token'] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $this->updateById($id, ['pay_token' => $token]);
        }
        return $token;
    }

    /** @return array<string,mixed>|null */
    public function findByPayToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return $this->db()->fetch(
            'SELECT i.*, c.company_name, c.contact_name FROM invoice i JOIN customer c ON c.id = i.customer_id WHERE i.pay_token = :t',
            ['t' => $token]
        );
    }

    public function openForMatching(): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT id, number, (gross_total_cents - paid_total_cents) AS open_cents
             FROM invoice
             WHERE is_locked = 1 AND status IN ('sent','overdue')
               AND (gross_total_cents - paid_total_cents) > 0
             ORDER BY invoice_date"
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'number' => (string) $r['number'], 'open_cents' => (int) $r['open_cents'],
        ], $rows);
    }

    /**
     * Markiert alle fälligen, noch offenen Rechnungen als 'overdue'.
     * Gibt die Anzahl der aktualisierten Rechnungen zurück.
     *
     * Hintergrund: Der Status wird sonst nur beim Erfassen einer Zahlung neu
     * berechnet. Damit eine nie angefasste Rechnung trotzdem überfällig wird,
     * läuft dieser Sweep beim Dashboard-Aufruf bzw. per Cron (bin/sweep.php).
     */
    public function markOverdue(): int
    {
        $stmt = $this->db()->query(
            "UPDATE invoice
                SET status = 'overdue', updated_at = :now
              WHERE is_locked = 1
                AND status = 'sent'
                AND due_date IS NOT NULL
                AND due_date < :today
                AND paid_total_cents < gross_total_cents",
            ['now' => date('Y-m-d H:i:s'), 'today' => date('Y-m-d')]
        );
        return $stmt->rowCount();
    }

    public function recalcPaymentStatus(int $id): void
    {
        $invoice = $this->find($id);
        if ($invoice === null || (int) $invoice['is_locked'] !== 1 || $invoice['status'] === 'cancelled') {
            return;
        }
        $paid = (int) $this->db()->fetchColumn(
            'SELECT COALESCE(SUM(amount_cents), 0) FROM payment WHERE invoice_id = :id',
            ['id' => $id]
        );
        $status = $invoice['status'];
        if ($paid >= (int) $invoice['gross_total_cents'] && (int) $invoice['gross_total_cents'] !== 0) {
            $status = 'paid';
        } elseif ($invoice['due_date'] !== null && $invoice['due_date'] < date('Y-m-d')) {
            $status = 'overdue';
        } else {
            $status = 'sent';
        }
        $this->updateById($id, ['paid_total_cents' => $paid, 'status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
