<?php

declare(strict_types=1);

namespace Nova\Services;

/**
 * Erzeugt ZUGFeRD-/Factur-X-XML im UN/CEFACT-CII-Format (Profil EN 16931).
 * Dieses XML wird in die Rechnungs-PDF eingebettet (PDF/A-3) – eine valide
 * hybride E-Rechnung. Beträge in Cent; alle Zeilen teilen den Rechnungs-USt-Satz.
 */
final class ZugferdService
{
    public const FILENAME = 'factur-x.xml';

    /**
     * @param array<string,mixed> $invoice
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $settings
     */
    public static function cii(array $invoice, array $items, array $settings): string
    {
        $isKU      = (int) $invoice['is_kleinunternehmer'] === 1;
        $vatRate   = $isKU ? 0 : (int) $invoice['vat_rate'];
        $catCode   = $isKU ? 'E' : 'S';
        $netLines  = (int) $invoice['net_total_cents'];
        $discount  = (int) ($invoice['discount_cents'] ?? 0);
        $base      = $netLines - $discount;
        $vat       = (int) $invoice['vat_total_cents'];
        $gross     = (int) $invoice['gross_total_cents'];
        $dueDate   = $invoice['due_date'] ?: $invoice['invoice_date'];

        $x = new \XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');

        $x->startElement('rsm:CrossIndustryInvoice');
        $x->writeAttribute('xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $x->writeAttribute('xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $x->writeAttribute('xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        // Kontext: Profil EN 16931 (COMFORT)
        $x->startElement('rsm:ExchangedDocumentContext');
        $x->startElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $x->writeElement('ram:ID', 'urn:cen.eu:en16931:2017');
        $x->endElement();
        $x->endElement();

        // Dokumentkopf
        $x->startElement('rsm:ExchangedDocument');
        $x->writeElement('ram:ID', (string) $invoice['number']);
        $x->writeElement('ram:TypeCode', '380');
        $x->startElement('ram:IssueDateTime');
        self::dateString($x, (string) $invoice['invoice_date']);
        $x->endElement();
        $x->endElement();

        $x->startElement('rsm:SupplyChainTradeTransaction');

        // Positionen
        $pos = 0;
        foreach ($items as $it) {
            $pos++;
            $x->startElement('ram:IncludedSupplyChainTradeLineItem');
            $x->startElement('ram:AssociatedDocumentLineDocument');
            $x->writeElement('ram:LineID', (string) $pos);
            $x->endElement();
            $x->startElement('ram:SpecifiedTradeProduct');
            $x->writeElement('ram:Name', (string) $it['description']);
            $x->endElement();
            $x->startElement('ram:SpecifiedLineTradeAgreement');
            $x->startElement('ram:NetPriceProductTradePrice');
            $x->writeElement('ram:ChargeAmount', self::amt((int) $it['unit_price_cents']));
            $x->endElement();
            $x->endElement();
            $x->startElement('ram:SpecifiedLineTradeDelivery');
            $x->startElement('ram:BilledQuantity');
            $x->writeAttribute('unitCode', self::unitCode((string) $it['unit']));
            $x->text(self::qty((float) $it['quantity']));
            $x->endElement();
            $x->endElement();
            $x->startElement('ram:SpecifiedLineTradeSettlement');
            $x->startElement('ram:ApplicableTradeTax');
            $x->writeElement('ram:TypeCode', 'VAT');
            $x->writeElement('ram:CategoryCode', $catCode);
            $x->writeElement('ram:RateApplicablePercent', self::rate($vatRate));
            $x->endElement();
            $x->startElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
            $x->writeElement('ram:LineTotalAmount', self::amt((int) $it['line_total_cents']));
            $x->endElement();
            $x->endElement();
            $x->endElement(); // IncludedSupplyChainTradeLineItem
        }

        // Kopf-Vereinbarung: Verkäufer / Käufer
        $x->startElement('ram:ApplicableHeaderTradeAgreement');
        self::sellerParty($x, $settings);
        self::buyerParty($x, $invoice);
        $x->endElement();

        // Lieferung (Pflichtelement, ggf. leer)
        $x->startElement('ram:ApplicableHeaderTradeDelivery');
        if (!empty($invoice['service_date_to']) || !empty($invoice['service_date_from'])) {
            $x->startElement('ram:ActualDeliverySupplyChainEvent');
            $x->startElement('ram:OccurrenceDateTime');
            self::dateString($x, (string) ($invoice['service_date_to'] ?: $invoice['service_date_from']));
            $x->endElement();
            $x->endElement();
        }
        $x->endElement();

        // Abrechnung
        $x->startElement('ram:ApplicableHeaderTradeSettlement');
        $x->writeElement('ram:InvoiceCurrencyCode', 'EUR');

        if (!empty($settings['iban'])) {
            $x->startElement('ram:SpecifiedTradeSettlementPaymentMeans');
            $x->writeElement('ram:TypeCode', '58'); // SEPA-Überweisung
            $x->startElement('ram:PayeePartyCreditorFinancialAccount');
            $x->writeElement('ram:IBANID', (string) $settings['iban']);
            $x->endElement();
            $x->endElement();
        }

        // USt-Aufschlüsselung (eine Gruppe – Nova nutzt einen Satz je Rechnung)
        $x->startElement('ram:ApplicableTradeTax');
        $x->writeElement('ram:CalculatedAmount', self::amt($vat));
        $x->writeElement('ram:TypeCode', 'VAT');
        if ($isKU) {
            $x->writeElement('ram:ExemptionReason', (string) ($settings['kleinunternehmer_note'] ?: 'Kleinunternehmer gemäß § 19 UStG'));
        }
        $x->writeElement('ram:BasisAmount', self::amt($base));
        $x->writeElement('ram:CategoryCode', $catCode);
        if ($isKU) {
            $x->writeElement('ram:ExemptionReasonCode', 'VATEX-EU-O');
        }
        $x->writeElement('ram:RateApplicablePercent', self::rate($vatRate));
        $x->endElement();

        // Dokument-Rabatt als AllowanceCharge
        if ($discount > 0) {
            $x->startElement('ram:SpecifiedTradeAllowanceCharge');
            $x->startElement('ram:ChargeIndicator');
            $x->writeElement('udt:Indicator', 'false');
            $x->endElement();
            $x->writeElement('ram:ActualAmount', self::amt($discount));
            $x->writeElement('ram:Reason', 'Rabatt');
            $x->startElement('ram:CategoryTradeTax');
            $x->writeElement('ram:TypeCode', 'VAT');
            $x->writeElement('ram:CategoryCode', $catCode);
            $x->writeElement('ram:RateApplicablePercent', self::rate($vatRate));
            $x->endElement();
            $x->endElement();
        }

        // Zahlungsziel
        $x->startElement('ram:SpecifiedTradePaymentTerms');
        $x->startElement('ram:DueDateDateTime');
        self::dateString($x, (string) $dueDate);
        $x->endElement();
        $x->endElement();

        // Summen
        $x->startElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $x->writeElement('ram:LineTotalAmount', self::amt($netLines));
        if ($discount > 0) {
            $x->writeElement('ram:AllowanceTotalAmount', self::amt($discount));
        }
        $x->writeElement('ram:TaxBasisTotalAmount', self::amt($base));
        $x->startElement('ram:TaxTotalAmount');
        $x->writeAttribute('currencyID', 'EUR');
        $x->text(self::amt($vat));
        $x->endElement();
        $x->writeElement('ram:GrandTotalAmount', self::amt($gross));
        $x->writeElement('ram:DuePayableAmount', self::amt($gross));
        $x->endElement();

        $x->endElement(); // ApplicableHeaderTradeSettlement
        $x->endElement(); // SupplyChainTradeTransaction
        $x->endElement(); // CrossIndustryInvoice
        $x->endDocument();

        return $x->outputMemory();
    }

    /** XMP-Erweiterung (Factur-X), die in die PDF/A-Metadaten eingefügt wird. */
    public static function xmpExtension(): string
    {
        return <<<'XML'
<rdf:Description rdf:about="" xmlns:fx="urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#">
  <fx:DocumentType>INVOICE</fx:DocumentType>
  <fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>
  <fx:Version>1.0</fx:Version>
  <fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>
</rdf:Description>
<rdf:Description rdf:about="" xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/" xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#" xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">
  <pdfaExtension:schemas>
    <rdf:Bag>
      <rdf:li rdf:parseType="Resource">
        <pdfaSchema:schema>Factur-X PDFA Extension Schema</pdfaSchema:schema>
        <pdfaSchema:namespaceURI>urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#</pdfaSchema:namespaceURI>
        <pdfaSchema:prefix>fx</pdfaSchema:prefix>
        <pdfaSchema:property>
          <rdf:Seq>
            <rdf:li rdf:parseType="Resource"><pdfaProperty:name>DocumentFileName</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>name of the embedded XML invoice file</pdfaProperty:description></rdf:li>
            <rdf:li rdf:parseType="Resource"><pdfaProperty:name>DocumentType</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>INVOICE</pdfaProperty:description></rdf:li>
            <rdf:li rdf:parseType="Resource"><pdfaProperty:name>Version</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>version of the Factur-X standard</pdfaProperty:description></rdf:li>
            <rdf:li rdf:parseType="Resource"><pdfaProperty:name>ConformanceLevel</pdfaProperty:name><pdfaProperty:valueType>Text</pdfaProperty:valueType><pdfaProperty:category>external</pdfaProperty:category><pdfaProperty:description>conformance level of the embedded XML</pdfaProperty:description></rdf:li>
          </rdf:Seq>
        </pdfaSchema:property>
      </rdf:li>
    </rdf:Bag>
  </pdfaExtension:schemas>
</rdf:Description>
XML;
    }

    /** @param array<string,mixed> $settings */
    private static function sellerParty(\XMLWriter $x, array $settings): void
    {
        $x->startElement('ram:SellerTradeParty');
        $x->writeElement('ram:Name', (string) $settings['company_name']);
        $x->startElement('ram:PostalTradeAddress');
        $x->writeElement('ram:PostcodeCode', (string) $settings['zip']);
        $x->writeElement('ram:LineOne', (string) $settings['address_line1']);
        $x->writeElement('ram:CityName', (string) $settings['city']);
        $x->writeElement('ram:CountryID', 'DE');
        $x->endElement();
        if (!empty($settings['vat_id'])) {
            $x->startElement('ram:SpecifiedTaxRegistration');
            $x->startElement('ram:ID');
            $x->writeAttribute('schemeID', 'VA');
            $x->text((string) $settings['vat_id']);
            $x->endElement();
            $x->endElement();
        }
        if (!empty($settings['tax_number'])) {
            $x->startElement('ram:SpecifiedTaxRegistration');
            $x->startElement('ram:ID');
            $x->writeAttribute('schemeID', 'FC');
            $x->text((string) $settings['tax_number']);
            $x->endElement();
            $x->endElement();
        }
        $x->endElement();
    }

    /** @param array<string,mixed> $invoice */
    private static function buyerParty(\XMLWriter $x, array $invoice): void
    {
        $x->startElement('ram:BuyerTradeParty');
        $x->writeElement('ram:Name', (string) ($invoice['company_name'] ?: $invoice['contact_name']));
        $x->startElement('ram:PostalTradeAddress');
        $x->writeElement('ram:PostcodeCode', (string) ($invoice['zip'] ?? ''));
        $x->writeElement('ram:LineOne', (string) ($invoice['address_line1'] ?? ''));
        $x->writeElement('ram:CityName', (string) ($invoice['city'] ?? ''));
        $x->writeElement('ram:CountryID', 'DE');
        $x->endElement();
        if (!empty($invoice['customer_vat_id'])) {
            $x->startElement('ram:SpecifiedTaxRegistration');
            $x->startElement('ram:ID');
            $x->writeAttribute('schemeID', 'VA');
            $x->text((string) $invoice['customer_vat_id']);
            $x->endElement();
            $x->endElement();
        }
        $x->endElement();
    }

    private static function dateString(\XMLWriter $x, string $date): void
    {
        $x->startElement('udt:DateTimeString');
        $x->writeAttribute('format', '102');
        $x->text(date('Ymd', strtotime($date) ?: time()));
        $x->endElement();
    }

    private static function amt(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function rate(int $rate): string
    {
        return number_format($rate, 2, '.', '');
    }

    private static function qty(float $q): string
    {
        return rtrim(rtrim(number_format($q, 4, '.', ''), '0'), '.') ?: '0';
    }

    /** UN/ECE-Mengeneinheit aus der freien Einheit ableiten (Fallback C62 = Stück). */
    private static function unitCode(string $unit): string
    {
        return match (mb_strtolower(trim($unit))) {
            'std', 'stunde', 'stunden', 'h'   => 'HUR',
            'tag', 'tage'                      => 'DAY',
            'kg'                               => 'KGM',
            'm', 'meter'                       => 'MTR',
            'm2', 'qm', 'm²'                   => 'MTK',
            'l', 'liter'                       => 'LTR',
            'km'                               => 'KMT',
            'monat', 'monate'                  => 'MON',
            default                            => 'C62',
        };
    }
}
