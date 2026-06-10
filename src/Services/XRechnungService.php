<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Erzeugt eine E-Rechnung im UBL-2.1-Format (Basis für XRechnung).
 *
 * Bewusst als erweiterbares Grundgerüst angelegt: die wichtigsten
 * Geschäftsfelder (BT-*) sind abgebildet. Für eine vollständig
 * konforme XRechnung/ZUGFeRD-Ausgabe kann dies später verfeinert bzw.
 * um eine PDF/A-3-Einbettung (ZUGFeRD) ergänzt werden.
 */
final class XRechnungService
{
    /**
     * @param array<string,mixed> $invoice  Rechnung inkl. Kundendaten (findWithCustomer)
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $settings
     */
    public static function generate(array $invoice, array $items, array $settings): string
    {
        $isKU       = (int) $invoice['is_kleinunternehmer'] === 1;
        $vatRate    = (int) $invoice['vat_rate'];
        $taxCat     = $isKU ? 'E' : 'S'; // E = steuerbefreit, S = Standardsatz
        $net        = self::amt((int) $invoice['net_total_cents']);
        $vat        = self::amt((int) $invoice['vat_total_cents']);
        $gross      = self::amt((int) $invoice['gross_total_cents']);
        $dueDate    = $invoice['due_date'] ?: $invoice['invoice_date'];

        $x = new \XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');

        $x->startElement('Invoice');
        $x->writeAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $x->writeAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->writeAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $x->writeElement('cbc:CustomizationID', 'urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_3.0');
        $x->writeElement('cbc:ProfileID', 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0');
        $x->writeElement('cbc:ID', (string) $invoice['number']);
        $x->writeElement('cbc:IssueDate', (string) $invoice['invoice_date']);
        $x->writeElement('cbc:DueDate', (string) $dueDate);
        $x->writeElement('cbc:InvoiceTypeCode', '380'); // Handelsrechnung
        $x->writeElement('cbc:DocumentCurrencyCode', 'EUR');

        if (!empty($invoice['service_date_from'])) {
            $x->startElement('cac:InvoicePeriod');
            $x->writeElement('cbc:StartDate', (string) $invoice['service_date_from']);
            $x->writeElement('cbc:EndDate', (string) ($invoice['service_date_to'] ?: $invoice['service_date_from']));
            $x->endElement();
        }

        // Verkäufer (AccountingSupplierParty)
        $x->startElement('cac:AccountingSupplierParty');
        $x->startElement('cac:Party');
        self::postalAddress($x, $settings['address_line1'], $settings['zip'], $settings['city']);
        $x->startElement('cac:PartyLegalEntity');
        $x->writeElement('cbc:RegistrationName', (string) $settings['company_name']);
        $x->endElement();
        if (!empty($settings['vat_id'])) {
            $x->startElement('cac:PartyTaxScheme');
            $x->writeElement('cbc:CompanyID', (string) $settings['vat_id']);
            $x->startElement('cac:TaxScheme');
            $x->writeElement('cbc:ID', 'VAT');
            $x->endElement();
            $x->endElement();
        }
        self::contact($x, $settings['owner_name'] ?: $settings['company_name'], $settings['email'], $settings['phone']);
        $x->endElement(); // Party
        $x->endElement(); // AccountingSupplierParty

        // Käufer (AccountingCustomerParty)
        $x->startElement('cac:AccountingCustomerParty');
        $x->startElement('cac:Party');
        self::postalAddress($x, (string) ($invoice['address_line1'] ?? ''), (string) ($invoice['zip'] ?? ''), (string) ($invoice['city'] ?? ''));
        $x->startElement('cac:PartyLegalEntity');
        $x->writeElement('cbc:RegistrationName', (string) ($invoice['company_name'] ?: $invoice['contact_name']));
        $x->endElement();
        $x->endElement(); // Party
        $x->endElement(); // AccountingCustomerParty

        // Zahlungsmittel
        if (!empty($settings['iban'])) {
            $x->startElement('cac:PaymentMeans');
            $x->writeElement('cbc:PaymentMeansCode', '58'); // SEPA-Überweisung
            $x->startElement('cac:PayeeFinancialAccount');
            $x->writeElement('cbc:ID', (string) $settings['iban']);
            $x->endElement();
            $x->endElement();
        }

        // Steueraufschlüsselung
        $x->startElement('cac:TaxTotal');
        self::amountEl($x, 'cbc:TaxAmount', $vat);
        $x->startElement('cac:TaxSubtotal');
        self::amountEl($x, 'cbc:TaxableAmount', $net);
        self::amountEl($x, 'cbc:TaxAmount', $vat);
        $x->startElement('cac:TaxCategory');
        $x->writeElement('cbc:ID', $taxCat);
        $x->writeElement('cbc:Percent', $isKU ? '0' : (string) $vatRate);
        if ($isKU) {
            $x->writeElement('cbc:TaxExemptionReasonCode', 'VATEX-EU-O');
            $x->writeElement('cbc:TaxExemptionReason', (string) ($settings['kleinunternehmer_note'] ?: 'Kleinunternehmer gemäß § 19 UStG'));
        }
        $x->startElement('cac:TaxScheme');
        $x->writeElement('cbc:ID', 'VAT');
        $x->endElement();
        $x->endElement(); // TaxCategory
        $x->endElement(); // TaxSubtotal
        $x->endElement(); // TaxTotal

        // Summen
        $x->startElement('cac:LegalMonetaryTotal');
        self::amountEl($x, 'cbc:LineExtensionAmount', $net);
        self::amountEl($x, 'cbc:TaxExclusiveAmount', $net);
        self::amountEl($x, 'cbc:TaxInclusiveAmount', $gross);
        self::amountEl($x, 'cbc:PayableAmount', $gross);
        $x->endElement();

        // Positionen
        foreach ($items as $i => $it) {
            $x->startElement('cac:InvoiceLine');
            $x->writeElement('cbc:ID', (string) ($i + 1));
            self::quantityEl($x, 'cbc:InvoicedQuantity', self::qty((float) $it['quantity']), (string) $it['unit']);
            self::amountEl($x, 'cbc:LineExtensionAmount', self::amt((int) $it['line_total_cents']));
            $x->startElement('cac:Item');
            $x->writeElement('cbc:Name', (string) $it['description']);
            $x->startElement('cac:ClassifiedTaxCategory');
            $x->writeElement('cbc:ID', $taxCat);
            $x->writeElement('cbc:Percent', $isKU ? '0' : (string) $vatRate);
            $x->startElement('cac:TaxScheme');
            $x->writeElement('cbc:ID', 'VAT');
            $x->endElement();
            $x->endElement(); // ClassifiedTaxCategory
            $x->endElement(); // Item
            $x->startElement('cac:Price');
            self::amountEl($x, 'cbc:PriceAmount', self::amt((int) $it['unit_price_cents']));
            $x->endElement();
            $x->endElement(); // InvoiceLine
        }

        $x->endElement(); // Invoice
        $x->endDocument();

        return $x->outputMemory();
    }

    private static function postalAddress(\XMLWriter $x, string $street, string $zip, string $city): void
    {
        $x->startElement('cac:PostalAddress');
        $x->writeElement('cbc:StreetName', $street);
        $x->writeElement('cbc:CityName', $city);
        $x->writeElement('cbc:PostalZone', $zip);
        $x->startElement('cac:Country');
        $x->writeElement('cbc:IdentificationCode', 'DE');
        $x->endElement();
        $x->endElement();
    }

    private static function contact(\XMLWriter $x, string $name, string $email, string $phone): void
    {
        $x->startElement('cac:Contact');
        $x->writeElement('cbc:Name', $name);
        if ($phone !== '') {
            $x->writeElement('cbc:Telephone', $phone);
        }
        if ($email !== '') {
            $x->writeElement('cbc:ElectronicMail', $email);
        }
        $x->endElement();
    }

    private static function amountEl(\XMLWriter $x, string $name, string $value): void
    {
        $x->startElement($name);
        $x->writeAttribute('currencyID', 'EUR');
        $x->text($value);
        $x->endElement();
    }

    private static function quantityEl(\XMLWriter $x, string $name, string $value, string $unit): void
    {
        $x->startElement($name);
        $x->writeAttribute('unitCode', 'C62'); // generische Einheit
        $x->text($value);
        $x->endElement();
    }

    private static function amt(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function qty(float $q): string
    {
        return rtrim(rtrim(number_format($q, 4, '.', ''), '0'), '.');
    }
}
