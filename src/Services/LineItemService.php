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
     * @param array<int,array<string,mixed>> $items
     * @return array{net_total_cents:int,vat_total_cents:int,gross_total_cents:int}
     */
    public static function totals(array $items, int $vatRate, bool $isKleinunternehmer): array
    {
        $net = 0;
        foreach ($items as $item) {
            $net += (int) $item['line_total_cents'];
        }
        $vat   = $isKleinunternehmer ? 0 : (int) round($net * $vatRate / 100);
        return [
            'net_total_cents'   => $net,
            'vat_total_cents'   => $vat,
            'gross_total_cents' => $net + $vat,
        ];
    }

    private static function toFloat(string $value): float
    {
        $value = str_replace(['.', ' '], '', trim($value)); // Tausenderpunkte
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }
}
