<?php
/** @var array<string,mixed> $invoice */
/** @var array<int,array<string,mixed>> $items */
/** @var array<string,mixed> $settings */
$inv = $invoice;
$s = $settings;
$isKU = (int) $inv['is_kleinunternehmer'] === 1;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11px; color: #1f2733; margin: 0; }
    .head { width: 100%; margin-bottom: 30px; }
    .head td { vertical-align: top; }
    .logo { max-height: 70px; }
    .company-name { font-size: 19px; font-weight: bold; color: #1f2733; }
    .sender { text-align: right; vertical-align: top; font-size: 10px; color: #555; line-height: 1.4; }
    .sender-line { font-size: 8px; color: #777; border-bottom: .5px solid #999; padding-bottom: 2px; margin-bottom: 4px; }
    .addr { margin-bottom: 28px; line-height: 1.45; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .meta { color: #555; margin-bottom: 18px; font-size: 10px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.items th { text-align: left; border-bottom: 1px solid #333; padding: 6px 4px; font-size: 9px; text-transform: uppercase; }
    table.items td { padding: 6px 4px; border-bottom: .5px solid #ddd; }
    .num { text-align: right; }
    .totals { width: 45%; margin-left: 55%; }
    .totals td { padding: 3px 4px; }
    .totals .grand td { border-top: 1px solid #333; font-weight: bold; font-size: 12px; }
    .note { margin-top: 16px; font-size: 10px; color: #444; }
    .pay { margin-top: 16px; font-size: 10px; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8.5px; color: #777; border-top: .5px solid #ccc; padding-top: 6px; }
</style>
</head>
<body>
    <div class="footer">
        <table style="width:100%"><tr>
            <td>
                <?= e($s['company_name']) ?>
                <?php if ($s['tax_number']): ?><br>Steuernr.: <?= e($s['tax_number']) ?><?php endif; ?>
                <?php if ($s['vat_id']): ?><br>USt-ID: <?= e($s['vat_id']) ?><?php endif; ?>
            </td>
            <td style="text-align:center">
                <?php if ($s['email']): ?><?= e($s['email']) ?><br><?php endif; ?>
                <?php if ($s['phone']): ?>Tel.: <?= e($s['phone']) ?><br><?php endif; ?>
                <?php if (!empty($s['website'])): ?><?= e($s['website']) ?><br><?php endif; ?>
                <?php if (!empty($s['social_media'])): ?><?= e($s['social_media']) ?><?php endif; ?>
            </td>
            <td style="text-align:right"><?php if ($s['bank_name']): ?><?= e($s['bank_name']) ?><br><?php endif; ?><?php if ($s['iban']): ?>IBAN: <?= e($s['iban']) ?><?php endif; ?><?php if ($s['bic']): ?><br>BIC: <?= e($s['bic']) ?><?php endif; ?></td>
        </tr></table>
    </div>

    <?= partial('pdf/_header', ['settings' => $s]) ?>

    <div class="addr">
        <div class="sender-line"><?= e($s['company_name']) ?> · <?= e($s['address_line1']) ?> · <?= e(trim($s['zip'] . ' ' . $s['city'])) ?></div>
        <strong><?= e($inv['company_name'] ?: $inv['contact_name']) ?></strong><br>
        <?php if ($inv['company_name'] && $inv['contact_name']): ?><?= e($inv['contact_name']) ?><br><?php endif; ?>
        <?= e($inv['address_line1']) ?><br>
        <?= e(trim(($inv['zip'] ?? '') . ' ' . ($inv['city'] ?? ''))) ?>
        <?php if (!empty($inv['customer_vat_id'])): ?><br>USt-ID: <?= e($inv['customer_vat_id']) ?><?php endif; ?>
    </div>

    <h1>Rechnung <?= e($inv['number'] ?: '(Entwurf)') ?></h1>
    <div class="meta">
        Rechnungsdatum: <?= dt($inv['invoice_date']) ?>
        <?php if ($inv['due_date']): ?> &nbsp;·&nbsp; Fällig bis: <?= dt($inv['due_date']) ?><?php endif; ?>
        <?php if ($inv['service_date_from']): ?><br>Leistungszeitraum: <?= dt($inv['service_date_from']) ?><?php if ($inv['service_date_to']): ?> – <?= dt($inv['service_date_to']) ?><?php endif; ?><?php endif; ?>
    </div>

    <?php if ($inv['intro_text']): ?><p><?= nl2br(e($inv['intro_text'])) ?></p><?php endif; ?>

    <table class="items">
        <thead>
            <tr><th>Pos.</th><th>Beschreibung</th><th class="num">Menge</th><th class="num">Einzelpreis</th><th class="num">Gesamt</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr>
                <td><?= (int) $it['position'] ?></td>
                <td><?= nl2br(e($it['description'])) ?></td>
                <td class="num"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 2, ',', '.'), '0'), ',')) ?> <?= e($it['unit']) ?></td>
                <td class="num"><?= money((int) $it['unit_price_cents']) ?></td>
                <td class="num"><?= money((int) $it['line_total_cents']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php $discount = (int) ($inv['discount_cents'] ?? 0); ?>
    <table class="totals">
        <?php if ($discount > 0): ?>
            <tr><td>Zwischensumme</td><td class="num"><?= money((int) $inv['net_total_cents']) ?></td></tr>
            <tr><td>Rabatt<?= (string) ($inv['discount_type'] ?? '') === 'percent' ? ' (' . rtrim(rtrim(number_format((int) $inv['discount_value'] / 100, 2, ',', ''), '0'), ',') . ' %)' : '' ?></td><td class="num">−<?= money($discount) ?></td></tr>
            <tr><td>Netto</td><td class="num"><?= money((int) $inv['net_total_cents'] - $discount) ?></td></tr>
        <?php else: ?>
            <tr><td>Netto</td><td class="num"><?= money((int) $inv['net_total_cents']) ?></td></tr>
        <?php endif; ?>
        <?php if (!$isKU): ?>
            <tr><td>USt <?= (int) $inv['vat_rate'] ?> %</td><td class="num"><?= money((int) $inv['vat_total_cents']) ?></td></tr>
        <?php endif; ?>
        <tr class="grand"><td>Gesamtbetrag</td><td class="num"><?= money((int) $inv['gross_total_cents']) ?></td></tr>
    </table>

    <?php if ($isKU): ?>
        <p class="note"><?= e($s['kleinunternehmer_note']) ?></p>
    <?php endif; ?>

    <?php if ($inv['status'] !== 'cancelled' && (int) $inv['gross_total_cents'] >= 0): ?>
        <p class="pay">Bitte überweisen Sie den Gesamtbetrag<?php if ($inv['due_date']): ?> bis zum <?= dt($inv['due_date']) ?><?php endif; ?>
        <?php if ($inv['iban'] ?? $s['iban']): ?> auf das Konto <?= e($s['iban']) ?> (<?= e($s['bank_name']) ?>)<?php endif; ?>
        unter Angabe der Rechnungsnummer <?= e($inv['number']) ?>.</p>
        <?php if ((int) ($inv['skonto_percent_bp'] ?? 0) > 0 && (int) ($inv['skonto_days'] ?? 0) > 0): ?>
            <?php $skontoAmt = (int) round((int) $inv['gross_total_cents'] * (int) $inv['skonto_percent_bp'] / 10000); ?>
            <p class="pay">Bei Zahlung innerhalb von <?= (int) $inv['skonto_days'] ?> Tagen gewähren wir <?= rtrim(rtrim(number_format((int) $inv['skonto_percent_bp'] / 100, 2, ',', ''), '0'), ',') ?> % Skonto (<?= money($skontoAmt) ?>); zahlbar dann <?= money((int) $inv['gross_total_cents'] - $skontoAmt) ?>.</p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($inv['footer_text']): ?><p class="note"><?= nl2br(e($inv['footer_text'])) ?></p><?php endif; ?>
</body>
</html>
