<?php

declare(strict_types=1);

namespace Nova\Models;

final class PaymentRepository extends BaseRepository
{
    protected string $table = 'payment';

    public function createPayment(int $invoiceId, string $paidOn, int $amountCents, string $method, string $note, string $externalRef = ''): int
    {
        return $this->insert([
            'invoice_id'   => $invoiceId,
            'paid_on'      => $paidOn,
            'amount_cents' => $amountCents,
            'method'       => $method,
            'note'         => $note,
            'external_ref' => $externalRef,
        ]);
    }

    /** Wurde eine Zahlung mit dieser externen Referenz schon verbucht? (Idempotenz) */
    public function existsExternalRef(string $ref): bool
    {
        if ($ref === '') {
            return false;
        }
        return (int) $this->db()->fetchColumn(
            'SELECT COUNT(*) FROM payment WHERE external_ref = :r',
            ['r' => $ref]
        ) > 0;
    }
}
