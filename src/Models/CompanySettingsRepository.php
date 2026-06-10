<?php

declare(strict_types=1);

namespace Nova\Models;

final class CompanySettingsRepository extends BaseRepository
{
    protected string $table = 'company_settings';

    /** Liefert die (einzige) Einstellungszeile; legt sie bei Bedarf an. */
    public function get(): array
    {
        $row = $this->find(1);
        if ($row === null) {
            $this->db()->query('INSERT INTO company_settings (id) VALUES (1)');
            $row = $this->find(1);
        }
        return $row ?? [];
    }

    /** Standard-Zahlarten, falls in den Einstellungen nichts gepflegt ist. */
    public const DEFAULT_PAYMENT_METHODS = ['Überweisung', 'Bar', 'EC-/Girocard', 'Kreditkarte', 'PayPal', 'Lastschrift'];

    // Standard-Textvorlagen für den E-Mail-Versand (Platzhalter s. Einstellungen).
    public const DEFAULT_EMAIL_SIGNATURE       = "Mit freundlichen Grüßen\n{firma}";
    public const DEFAULT_INVOICE_EMAIL_SUBJECT = 'Rechnung {nummer}';
    public const DEFAULT_INVOICE_EMAIL_BODY    = "Guten Tag {kunde},\n\nanbei erhalten Sie unsere Rechnung {nummer} vom {datum}.\nWir bitten um Begleichung bis zum {faellig}.";
    public const DEFAULT_QUOTE_EMAIL_SUBJECT   = 'Angebot {nummer}';
    public const DEFAULT_QUOTE_EMAIL_BODY      = "Guten Tag {kunde},\n\nvielen Dank für Ihr Interesse. Anbei erhalten Sie unser Angebot {nummer}.\nBei Fragen stehen wir Ihnen gern zur Verfügung.";

    /**
     * Liefert die gepflegten Zahlarten als Liste (Fallback: Standardliste).
     *
     * @return array<int,string>
     */
    public function paymentMethods(): array
    {
        $raw = (string) ($this->get()['payment_methods'] ?? '');
        $list = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
        return $list === [] ? self::DEFAULT_PAYMENT_METHODS : $list;
    }

    /** @param array<string,mixed> $data */
    public function update(array $data): void
    {
        $this->get(); // stellt sicher, dass die Einstellungszeile (id=1) existiert
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->updateById(1, $data);
    }
}
