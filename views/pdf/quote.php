<?php
/** @var array<string,mixed> $quote */
/** @var array<int,array<string,mixed>> $items */
/** @var array<string,mixed> $settings */
$q = $quote;
$s = $settings;
$isKU = (int) $q['is_kleinunternehmer'] === 1;
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
    .note { margin-top: 18px; font-size: 10px; color: #444; }
    .footer { margin-top: 36px; font-size: 9px; color: #777; border-top: .5px solid #ccc; padding-top: 6px; }
</style>
</head>
<body>
    <?= partial('pdf/_header', ['settings' => $s]) ?>

    <div class="addr">
        <div class="sender-line"><?= e($s['company_name']) ?> · <?= e($s['address_line1']) ?> · <?= e(trim($s['zip'] . ' ' . $s['city'])) ?></div>
        <strong><?= e($q['company_name'] ?: $q['contact_name']) ?></strong><br>
        <?php if ($q['company_name'] && $q['contact_name']): ?><?= e($q['contact_name']) ?><br><?php endif; ?>
        <?= e($q['address_line1']) ?><br>
        <?= e(trim(($q['zip'] ?? '') . ' ' . ($q['city'] ?? ''))) ?>
    </div>

    <h1>Angebot <?= e($q['number'] ?: '(Entwurf)') ?></h1>
    <div class="meta">
        Datum: <?= dt($q['quote_date']) ?>
        <?php if ($q['valid_until']): ?> &nbsp;·&nbsp; Gültig bis: <?= dt($q['valid_until']) ?><?php endif; ?>
    </div>

    <?php if ($q['intro_text']): ?><p><?= nl2br(e($q['intro_text'])) ?></p><?php endif; ?>

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

    <table class="totals">
        <tr><td>Netto</td><td class="num"><?= money((int) $q['net_total_cents']) ?></td></tr>
        <?php if (!$isKU): ?>
            <tr><td>USt <?= (int) $q['vat_rate'] ?> %</td><td class="num"><?= money((int) $q['vat_total_cents']) ?></td></tr>
        <?php endif; ?>
        <tr class="grand"><td>Gesamt</td><td class="num"><?= money((int) $q['gross_total_cents']) ?></td></tr>
    </table>

    <?php if ($isKU): ?>
        <p class="note"><?= e($s['kleinunternehmer_note']) ?></p>
    <?php endif; ?>
    <?php if ($q['footer_text']): ?><p class="note"><?= nl2br(e($q['footer_text'])) ?></p><?php endif; ?>

    <div class="footer">
        <?= e($s['company_name']) ?>
        <?php if ($s['tax_number']): ?> · Steuernr.: <?= e($s['tax_number']) ?><?php endif; ?>
        <?php if ($s['iban']): ?> · IBAN: <?= e($s['iban']) ?><?php endif; ?>
        <?php
        $contact = array_filter([
            $s['email'] ?? '',
            ($s['phone'] ?? '') !== '' ? 'Tel.: ' . $s['phone'] : '',
            $s['website'] ?? '',
            $s['social_media'] ?? '',
        ]);
        ?>
        <?php if ($contact !== []): ?><br><?= e(implode(' · ', $contact)) ?><?php endif; ?>
    </div>
</body>
</html>
