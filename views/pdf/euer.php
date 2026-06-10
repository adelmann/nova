<?php
/** @var int $year */
/** @var array{income:int,expense:int,profit:int} $summary */
/** @var array<int,array{income:int,expense:int}> $months */
/** @var array{income:array<string,int>,expense:array<string,int>} $categories */
/** @var array<string,mixed> $settings */
$s = $settings;
$monthNames = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
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
    h1 { font-size: 17px; margin: 0 0 2px; }
    .sub { color: #666; margin-bottom: 16px; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    th { text-align: left; border-bottom: 1px solid #333; padding: 5px 4px; font-size: 9px; text-transform: uppercase; }
    td { padding: 5px 4px; border-bottom: .5px solid #ddd; }
    .num { text-align: right; }
    tfoot td { border-top: 1px solid #333; font-weight: bold; }
    .summary td { font-size: 12px; }
    .note { font-size: 9px; color: #777; margin-top: 20px; }
</style></head><body>
    <?= partial('pdf/_header', ['settings' => $s]) ?>
    <h1>Einnahmen-Überschuss-Rechnung <?= $year ?></h1>
    <div class="sub"><?= e($s['company_name']) ?> · <?= e($s['owner_name']) ?><?php if ($s['tax_number']): ?> · Steuernr. <?= e($s['tax_number']) ?><?php endif; ?></div>

    <table class="summary">
        <tr><td>Summe Einnahmen</td><td class="num"><?= money($summary['income']) ?></td></tr>
        <tr><td>Summe Ausgaben</td><td class="num"><?= money($summary['expense']) ?></td></tr>
        <tr><td><strong>Gewinn / Verlust</strong></td><td class="num"><strong><?= money($summary['profit']) ?></strong></td></tr>
    </table>

    <h3 style="font-size:12px;">Monatsübersicht</h3>
    <table>
        <thead><tr><th>Monat</th><th class="num">Einnahmen</th><th class="num">Ausgaben</th><th class="num">Saldo</th></tr></thead>
        <tbody>
        <?php for ($m = 1; $m <= 12; $m++): $v = $months[$m]; ?>
            <tr><td><?= $monthNames[$m] ?></td><td class="num"><?= money($v['income']) ?></td><td class="num"><?= money($v['expense']) ?></td><td class="num"><?= money($v['income'] - $v['expense']) ?></td></tr>
        <?php endfor; ?>
        </tbody>
        <tfoot><tr><td>Gesamt</td><td class="num"><?= money($summary['income']) ?></td><td class="num"><?= money($summary['expense']) ?></td><td class="num"><?= money($summary['profit']) ?></td></tr></tfoot>
    </table>

    <?php if ($categories['expense'] !== []): ?>
    <h3 style="font-size:12px;">Ausgaben nach Kategorie</h3>
    <table>
        <thead><tr><th>Kategorie</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($categories['expense'] as $cat => $sum): ?>
            <tr><td><?= e($cat) ?></td><td class="num"><?= money($sum) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php
    $entries = $entries ?? [];
    if ($entries !== []):
        $groups = ['income' => [], 'expense' => []];
        foreach ($entries as $row) { $groups[$row['type']][] = $row; }
    ?>
        <div style="page-break-before: always;"></div>
        <h1>Einzelaufstellung der Buchungen <?= $year ?></h1>
        <div class="sub">Kontennachweis zur EÜR – alle gebuchten Einnahmen und Ausgaben im Detail.</div>

        <?php foreach (['income' => 'Einnahmen', 'expense' => 'Ausgaben'] as $type => $label): ?>
            <?php if ($groups[$type] === []) { continue; } ?>
            <h3 style="font-size:12px; margin-top:14px;"><?= $label ?></h3>
            <table>
                <thead><tr><th style="width:14%">Datum</th><th style="width:24%">Kategorie</th><th>Beschreibung</th><th class="num" style="width:16%">Betrag</th></tr></thead>
                <tbody>
                <?php $sum = 0; foreach ($groups[$type] as $row): $sum += abs((int) $row['amount_cents']); ?>
                    <tr>
                        <td><?= dt($row['entry_date']) ?></td>
                        <td><?= e($row['category']) ?></td>
                        <td><?= e($row['description']) ?></td>
                        <td class="num"><?= money(abs((int) $row['amount_cents'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td colspan="3">Summe <?= $label ?></td><td class="num"><?= money($sum) ?></td></tr></tfoot>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
</body></html>
