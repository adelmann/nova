<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\DB;
use Nova\Core\Format;
use Nova\Models\AssetRepository;

/**
 * Abschreibung (AfA) für Anlagegüter. Berechnet den AfA-Plan und bucht die
 * jährliche Abschreibung zum Jahresende (31.12.) ins Buchungsjournal – damit
 * fließt sie automatisch in die EÜR. GWG werden im Anschaffungsjahr voll
 * abgeschrieben, lineare AfA zeitanteilig (pro rata temporis) ab dem Kaufmonat.
 */
final class DepreciationService
{
    public const CATEGORY = 'Abschreibungen (AfA)';

    /** GWG-Grenze (netto) seit 2018: 800 €. */
    public const GWG_LIMIT_CENTS = 800_00;

    /**
     * AfA-Plan: Kalenderjahr => Abschreibungsbetrag (Cent). Die Summe ergibt
     * exakt die Anschaffungskosten.
     *
     * @return array<int,int>
     */
    public static function schedule(int $costCents, string $acquiredDate, int $lifeYears, string $method): array
    {
        $costCents = max(0, $costCents);
        $acqYear   = (int) substr($acquiredDate, 0, 4);
        $acqMonth  = (int) substr($acquiredDate, 5, 2);
        if ($acqYear <= 0) {
            return [];
        }
        if ($method === 'gwg' || $lifeYears <= 1) {
            return [$acqYear => $costCents];
        }

        $life     = max(1, $lifeYears);
        $annual   = (int) round($costCents / $life);
        $monthsY1 = 13 - max(1, min(12, $acqMonth)); // Monate inkl. Anschaffungsmonat
        $schedule = [];

        $first = (int) round($annual * $monthsY1 / 12);
        $first = min($first, $costCents);
        $schedule[$acqYear] = $first;
        $booked = $first;

        $year = $acqYear;
        while ($booked < $costCents) {
            $year++;
            $next = min($annual, $costCents - $booked);
            $schedule[$year] = ($schedule[$year] ?? 0) + $next;
            $booked += $next;
        }
        return $schedule;
    }

    /** Restbuchwert zum Ende des angegebenen Jahres (Cent). */
    public static function bookValue(array $asset, int $asOfYear): int
    {
        $schedule = self::schedule((int) $asset['cost_cents'], (string) $asset['acquired_date'], (int) $asset['useful_life_years'], (string) $asset['method']);
        $written = 0;
        foreach ($schedule as $year => $amount) {
            if ($year <= $asOfYear) {
                $written += $amount;
            }
        }
        return max(0, (int) $asset['cost_cents'] - $written);
    }

    /** Jahre, für die bereits eine AfA-Buchung existiert. @return array<int,int> Jahr => gebuchter Betrag */
    public static function bookedYears(int $assetId): array
    {
        $rows = DB::getInstance()->fetchAll(
            "SELECT CAST(strftime('%Y', entry_date) AS INTEGER) AS y, -SUM(amount_cents) AS s
             FROM ledger_entry WHERE reference_type = 'asset' AND reference_id = :id GROUP BY y",
            ['id' => $assetId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['y']] = (int) $r['s'];
        }
        return $out;
    }

    /**
     * Bucht alle fälligen AfA-Jahre (31.12. <= heute), die noch nicht gebucht
     * sind. Optional auf ein Anlagegut beschränkt. Idempotent.
     *
     * @return array<int,string> Protokollzeilen
     */
    public static function bookDueYears(?int $assetId = null): array
    {
        $repo  = new AssetRepository();
        $today = date('Y-m-d');
        $log   = [];

        $assets = $assetId !== null
            ? array_filter([$repo->find($assetId)])
            : $repo->allOrdered();

        foreach ($assets as $asset) {
            $aid      = (int) $asset['id'];
            $schedule = self::schedule((int) $asset['cost_cents'], (string) $asset['acquired_date'], (int) $asset['useful_life_years'], (string) $asset['method']);
            $booked   = self::bookedYears($aid);
            foreach ($schedule as $year => $amount) {
                if ($amount <= 0 || isset($booked[$year])) {
                    continue;
                }
                $yearEnd = $year . '-12-31';
                if ($yearEnd > $today) {
                    continue; // Jahr noch nicht abgeschlossen
                }
                LedgerService::recordExpense(
                    $yearEnd,
                    $amount,
                    'asset',
                    $aid,
                    self::CATEGORY,
                    'AfA ' . ($asset['name'] ?: ('Anlage #' . $aid)) . ' ' . $year
                );
                AuditService::record('create', 'asset', $aid, ['afa_year' => $year, 'amount' => $amount], null);
                $log[] = "Anlage #{$aid}: AfA {$year} gebucht (" . Format::money($amount) . ').';
            }
        }
        return $log;
    }
}
