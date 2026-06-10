<?php

declare(strict_types=1);

namespace Nova\Models;

final class PaymentRepository extends BaseRepository
{
    protected string $table = 'payment';

    /** @param array<string,mixed> $data */
    public function createPayment(int $invoiceId, string $paidOn, int $amountCents, string $method, string $note): int
    {
        return $this->insert([
            'invoice_id'   => $invoiceId,
            'paid_on'      => $paidOn,
            'amount_cents' => $amountCents,
            'method'       => $method,
            'note'         => $note,
        ]);
    }
}
