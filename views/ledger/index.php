<?php
/** @var array<int,array<string,mixed>> $entries */
/** @var array<int,int> $years */
/** @var int $year */
/** @var int $income */
/** @var int $expense */
?>
<div class="toolbar">
    <form method="get" action="/buchhaltung" style="display:flex; gap:8px; align-items:center;">
        <label class="muted" for="jahr" style="font-size:13px;">Jahr</label>
        <select name="jahr" id="jahr" style="width:auto;" onchange="this.form.submit()">
            <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a href="/exporte/journal?jahr=<?= $year ?>" class="btn btn-secondary">CSV-Export</a>
</div>

<div class="cards">
    <div class="card"><div class="label">Einnahmen <?= $year ?></div><div class="value pos"><?= money($income) ?></div></div>
    <div class="card"><div class="label">Ausgaben <?= $year ?></div><div class="value"><?= money(-$expense) ?></div></div>
    <div class="card"><div class="label">Saldo</div><div class="value <?= ($income + $expense) < 0 ? 'neg' : 'pos' ?>"><?= money($income + $expense) ?></div></div>
</div>

<p class="help">Das Buchungsjournal ist unveränderbar (GoBD): Einträge können weder bearbeitet noch gelöscht werden. Korrekturen erfolgen ausschließlich über Gegenbuchungen.</p>

<?php if ($entries === []): ?>
    <div class="table-wrap"><div class="empty">Keine Buchungen in <?= $year ?>.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Datum</th><th>Typ</th><th>Kategorie</th><th>Beschreibung</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($entries as $e): ?>
            <tr>
                <td><?= dt($e['entry_date']) ?></td>
                <td><span class="badge badge-<?= $e['type'] === 'income' ? 'paid' : 'draft' ?>"><?= $e['type'] === 'income' ? 'Einnahme' : 'Ausgabe' ?></span></td>
                <td><?= e($e['category']) ?></td>
                <td><?= e($e['description']) ?></td>
                <td class="num" style="<?= (int) $e['amount_cents'] < 0 ? 'color:var(--error)' : '' ?>"><?= money((int) $e['amount_cents']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
