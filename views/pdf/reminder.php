<?php
/** @var array<string,mixed> $invoice */
/** @var int $level */
/** @var int $offen */
/** @var int $feeCents */
/** @var array<string,mixed> $settings */
use Nova\Controllers\ReminderController;
$inv = $invoice;
$s = $settings;
$label = ReminderController::levelLabel($level);
$total = $offen + $feeCents;
?>
<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 11px; color: #1f2733; }
    .head { width: 100%; margin-bottom: 30px; }
    .head td { vertical-align: top; }
    .logo { max-height: 70px; }
    .company-name { font-size: 19px; font-weight: bold; color: #1f2733; }
    .sender { text-align: right; vertical-align: top; font-size: 10px; color: #555; line-height: 1.4; }
    .sender-line { font-size: 8px; color: #777; border-bottom: .5px solid #999; padding-bottom: 2px; margin-bottom: 4px; }
    .addr { margin-bottom: 28px; line-height: 1.45; }
    h1 { font-size: 17px; margin: 0 0 4px; }
    .meta { color: #555; margin-bottom: 18px; font-size: 10px; }
    table.sum { width: 50%; margin-top: 12px; }
    table.sum td { padding: 3px 4px; }
    table.sum .grand td { border-top: 1px solid #333; font-weight: bold; }
    p { line-height: 1.5; }
</style></head><body>
    <?= partial('pdf/_header', ['settings' => $s]) ?>

    <div class="addr">
        <div class="sender-line"><?= e($s['company_name']) ?> · <?= e($s['address_line1']) ?></div>
        <strong><?= e($inv['company_name'] ?: $inv['contact_name']) ?></strong><br>
        <?php if ($inv['company_name'] && $inv['contact_name']): ?><?= e($inv['contact_name']) ?><br><?php endif; ?>
        <?= e($inv['address_line1']) ?><br><?= e(trim(($inv['zip'] ?? '').' '.($inv['city'] ?? ''))) ?>
    </div>

    <h1><?= e($label) ?></h1>
    <div class="meta">zu Rechnung <?= e($inv['number']) ?> vom <?= dt($inv['invoice_date']) ?> · Datum: <?= date('d.m.Y') ?></div>

    <p>Sehr geehrte Damen und Herren,</p>
    <p>
        <?php if ($level === 1): ?>
            unsere oben genannte Rechnung ist seit dem <?= dt($inv['due_date']) ?> fällig und bislang noch nicht beglichen. Wir bitten Sie höflich, den offenen Betrag zeitnah auszugleichen.
        <?php else: ?>
            trotz unserer bisherigen Erinnerung konnten wir bis heute keinen Zahlungseingang zu der oben genannten Rechnung feststellen. Wir fordern Sie hiermit auf, den offenen Betrag umgehend zu begleichen.
        <?php endif; ?>
    </p>

    <table class="sum">
        <tr><td>Offener Rechnungsbetrag</td><td class="num" style="text-align:right"><?= money($offen) ?></td></tr>
        <?php if ($feeCents > 0): ?><tr><td>Mahngebühr</td><td class="num" style="text-align:right"><?= money($feeCents) ?></td></tr><?php endif; ?>
        <tr class="grand"><td>Zu zahlen</td><td class="num" style="text-align:right"><?= money($total) ?></td></tr>
    </table>

    <p>Bitte überweisen Sie den Betrag bis zum <strong><?= date('d.m.Y', strtotime('+7 days')) ?></strong> auf folgendes Konto:<br>
        <?= e($s['bank_name']) ?> · IBAN <?= e($s['iban']) ?><?php if ($s['bic']): ?> · BIC <?= e($s['bic']) ?><?php endif; ?><br>
        Verwendungszweck: <?= e($inv['number']) ?></p>

    <p>Sollte sich Ihre Zahlung mit diesem Schreiben überschnitten haben, betrachten Sie es bitte als gegenstandslos.</p>
    <p>Mit freundlichen Grüßen<br><?= e($s['owner_name'] ?: $s['company_name']) ?></p>
</body></html>
