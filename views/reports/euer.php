<?php
/** @var array<int,int> $years */
/** @var int $year */
/** @var array{income:int,expense:int,profit:int} $summary */
/** @var array<int,array{income:int,expense:int}> $months */
/** @var array{income:array<string,int>,expense:array<string,int>} $categories */
$monthNames = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
?>
<div class="toolbar">
    <form method="get" action="/auswertungen" style="display:flex; gap:8px; align-items:center;">
        <label class="muted" for="jahr" style="font-size:13px;">Jahr</label>
        <select name="jahr" id="jahr" style="width:auto;" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
        </select>
    </form>
    <div style="display:flex; gap:8px;">
        <a href="/auswertungen/csv?jahr=<?= $year ?>" class="btn btn-secondary">CSV</a>
        <a href="/auswertungen/pdf?jahr=<?= $year ?>&amp;_=<?= time() ?>" class="btn btn-secondary" target="_blank">PDF</a>
    </div>
</div>

<div class="cards">
    <div class="card"><div class="label">Einnahmen <?= $year ?></div><div class="value pos"><?= money($summary['income']) ?></div></div>
    <div class="card"><div class="label">Ausgaben <?= $year ?></div><div class="value"><?= money($summary['expense']) ?></div></div>
    <div class="card"><div class="label">Gewinn (EÜR)</div><div class="value <?= $summary['profit'] < 0 ? 'neg' : 'pos' ?>"><?= money($summary['profit']) ?></div><div class="sub">Einnahmen − Ausgaben</div></div>
</div>

<div class="panel">
    <h2>Monatsübersicht</h2>
    <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
        <table>
            <thead><tr><th>Monat</th><th class="num">Einnahmen</th><th class="num">Ausgaben</th><th class="num">Saldo</th></tr></thead>
            <tbody>
            <?php for ($m = 1; $m <= 12; $m++): $v = $months[$m]; $saldo = $v['income'] - $v['expense']; ?>
                <tr>
                    <td><?= $monthNames[$m] ?> <?= $year ?></td>
                    <td class="num"><?= money($v['income']) ?></td>
                    <td class="num"><?= money($v['expense']) ?></td>
                    <td class="num" style="<?= $saldo < 0 ? 'color:var(--error)' : '' ?>"><?= money($saldo) ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr><th>Gesamt</th><th class="num"><?= money($summary['income']) ?></th><th class="num"><?= money($summary['expense']) ?></th><th class="num"><?= money($summary['profit']) ?></th></tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="panel">
    <h2>Ausgaben nach Kategorie</h2>
    <?php if ($categories['expense'] === []): ?>
        <p class="muted">Keine Ausgaben erfasst.</p>
    <?php else: ?>
        <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
            <table>
                <thead><tr><th>Kategorie</th><th class="num">Betrag</th></tr></thead>
                <tbody>
                <?php foreach ($categories['expense'] as $cat => $sum): ?>
                    <tr><td><?= e($cat) ?></td><td class="num"><?= money($sum) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<p class="help">Hinweis: Diese Auswertung dient der Orientierung und ersetzt keine Steuerberatung.</p>
