<?php

declare(strict_types=1);

namespace Nova\Models;

final class ImportProfileRepository extends BaseRepository
{
    protected string $table = 'import_profile';

    /** @return array<int,array<string,mixed>> */
    public function allOrdered(): array
    {
        return $this->db()->fetchAll('SELECT * FROM import_profile ORDER BY name');
    }

    /** @param array<string,mixed> $data */
    public function createFromInput(array $data): int
    {
        return $this->insert($this->fillable($data));
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function fillable(array $data): array
    {
        $delim = (string) ($data['delimiter'] ?? ';');
        if (!in_array($delim, [';', ',', "\t"], true)) {
            $delim = ';';
        }
        return [
            'name'        => trim((string) ($data['name'] ?? '')) ?: 'Profil',
            'delimiter'   => $delim,
            'has_header'  => !empty($data['has_header']) ? 1 : 0,
            'col_date'    => max(1, (int) ($data['col_date'] ?? 1)),
            'col_amount'  => max(1, (int) ($data['col_amount'] ?? 4)),
            'col_purpose' => max(1, (int) ($data['col_purpose'] ?? 3)),
        ];
    }

    /**
     * Eingebaute Format-Vorlagen (befüllen das Formular, werden nicht gespeichert).
     * Spaltennummern sind übliche Defaults und ggf. an den eigenen Export anzupassen.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function presets(): array
    {
        return [
            'generic_semicolon' => ['label' => 'Generisch (Semikolon)', 'delimiter' => ';', 'has_header' => 1, 'col_date' => 1, 'col_amount' => 4, 'col_purpose' => 3],
            'generic_comma'     => ['label' => 'Generisch (Komma)',     'delimiter' => ',', 'has_header' => 1, 'col_date' => 1, 'col_amount' => 4, 'col_purpose' => 3],
            'sparkasse_camt'    => ['label' => 'Sparkasse (CAMT-CSV)',   'delimiter' => ';', 'has_header' => 1, 'col_date' => 2, 'col_amount' => 15, 'col_purpose' => 5],
            'dkb'               => ['label' => 'DKB (CSV)',              'delimiter' => ';', 'has_header' => 1, 'col_date' => 1, 'col_amount' => 8, 'col_purpose' => 5],
            'paypal'            => ['label' => 'PayPal (CSV)',           'delimiter' => ',', 'has_header' => 1, 'col_date' => 1, 'col_amount' => 8, 'col_purpose' => 4],
        ];
    }
}
