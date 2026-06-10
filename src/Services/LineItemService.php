<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\Format;

/**
 * Verarbeitet Positionszeilen (aus Formular-Arrays) und berechnet die Summen
 * für Angebote und Rechnungen. Beträge intern in Cent.
 */
final class LineItemService
{
    /**
     * Wandelt parallele Formular-Arrays in eine normalisierte Positionsliste um.
     * Leere Zeilen (ohne Beschreibung) werden verworfen.
     *
     * @param array<string,mixed> $post
     * @return array<int,array{position:int,description:string,quantity:float,unit:string,unit_price_cents:int,line_total_cents:int}>
     */
    public static function parse(array $post): array
    {
        $descriptions = (array) ($post['item_description'] ?? []);
        $quantities   = (array) ($post['item_quantity'] ?? []);
        $units        = (array) ($post['item_unit'] ?? []);
        $prices       = (array) ($post['item_unit_price'] ?? []);

        $items = [];
        $pos   = 0;
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty   = self::toFloat((string) ($quantities[$i] ?? '1'));
            $price = Format::toCents((string) ($prices[$i] ?? '0'));
            $items[] = [
                'position'         => ++$pos,
                'description'      => $desc,
                'quantity'         => $qty,
                'unit'             => trim((string) ($units[$i] ?? 'Stk')) ?: 'Stk',
                'unit_price_cents' => $price,
                'line_total_cents' => (int) round($qty * $price),
            ];
        }
        return $items;
    }

    /**
     * Berechnet Netto/USt/Brutto aus Positionen.
     *
     * net_total_cents bleibt die Positionssumme (vor Rabatt); discount_cents ist
     * der Nachlass; USt/Brutto werden auf die Bemessungsgrundlage nach Rabatt
     * gerechnet (so bleibt die XRechnung über eine AllowanceCharge konsistent).
     *
     * @param array<int,array<string,mixed>> $items
     * @return array{net_total_cents:int,discount_cents:int,vat_total_cents:int,gross_total_cents:int}
     */
    public static function totals(array $items, int $vatRate, bool $isKleinunternehmer, string $discountType = 'none', int $discountValue = 0): array
    {
        $net = 0;
        foreach ($items as $item) {
            $net += (int) $item['line_total_cents'];
        }
        $discount = self::discountCents($net, $discountType, $discountValue);
        $base     = $net - $discount;
        $vat      = $isKleinunternehmer ? 0 : (int) round($base * $vatRate / 100);
        return [
            'net_total_cents'   => $net,
            'discount_cents'    => $discount,
            'vat_total_cents'   => $vat,
            'gross_total_cents' => $base + $vat,
        ];
    }

    /**
     * Rabattbetrag (Cent) aus Positionssumme. percent: Wert in Basispunkten
     * (1000 = 10 %), amount: Wert in Cent. Nie größer als die Summe selbst.
     */
    public static function discountCents(int $netCents, string $type, int $value): int
    {
        $value = max(0, $value);
        $d = match ($type) {
            'percent' => (int) round($netCents * $value / 10000),
            'amount'  => $value,
            default   => 0,
        };
        return max(0, min($netCents, $d));
    }

    private static function toFloat(string $value): float
    {
        $value = str_replace(['.', ' '], '', trim($value)); // Tausenderpunkte
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }
}
