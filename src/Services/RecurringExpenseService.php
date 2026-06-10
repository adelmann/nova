<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\Format;
use Nova\Models\ExpenseRepository;
use Nova\Models\RecurringExpenseRepository;
use Nova\Models\VendorRepository;

/**
 * Erzeugt aus fälligen Dauerausgaben-Profilen jeweils eine bezahlte Ausgabe und
 * bucht sie ins Journal (EÜR). Wird vom Cron (bin/sweep.php) aufgerufen.
 */
final class RecurringExpenseService
{
    /** @return array<int,string> Protokollzeilen */
    public static function runDue(): array
    {
        $repo    = new RecurringExpenseRepository();
        $expRepo = new ExpenseRepository();
        $vendors = new VendorRepository();
        $log     = [];

        foreach ($repo->due() as $profile) {
            $rid    = (int) $profile['id'];
            $amount = (int) $profile['amount_cents'];

            if ($amount <= 0) {
                $repo->advance($rid, self::nextDate((string) $profile['next_date'], (string) $profile['interval_unit']));
                $log[] = "Dauerausgabe #{$rid}: Betrag 0 – übersprungen.";
                continue;
            }

            if (trim((string) $profile['supplier']) !== '') {
                $vendors->ensure((string) $profile['supplier']);
            }

            $expId = $expRepo->createFromInput([
                'expense_date' => (string) $profile['next_date'],
                'supplier'     => (string) $profile['supplier'],
                'category'     => (string) $profile['category'],
                'tax_category' => (string) $profile['tax_category'],
                'amount'       => Format::amount($amount),
                'vat_rate'     => (int) $profile['vat_rate'],
                'method'       => (string) $profile['method'],
                'status'       => 'paid',
                'note'         => trim((string) ($profile['note'] ?: $profile['title'])),
            ]);

            $e = $expRepo->find($expId);
            LedgerService::syncExpense(
                $expId,
                -$amount,
                (string) $e['expense_date'],
                (string) ($e['tax_category'] ?: ($e['category'] ?: 'Sonstiges')),
                trim(((string) ($e['supplier'] ?: 'Dauerausgabe')) . ' – ' . (string) ($e['note'] ?: $profile['title']))
            );

            AuditService::record('create', 'expense', $expId, ['recurring_expense' => $rid], null);
            $repo->advance($rid, self::nextDate((string) $profile['next_date'], (string) $profile['interval_unit']));
            $log[] = "Dauerausgabe #{$rid} → Ausgabe #{$expId} (" . Format::money($amount) . ').';
        }

        return $log;
    }

    private static function nextDate(string $current, string $unit): string
    {
        $add = match ($unit) {
            'quarter' => '+3 months',
            'year'    => '+1 year',
            default   => '+1 month',
        };
        return date('Y-m-d', strtotime($current . ' ' . $add) ?: time());
    }
}
