<?php
/** @var array<int,array<string,mixed>> $expenses */
/** @var string $q */
/** @var int|null $jahr */
/** @var int $sum */
?>
<div class="toolbar">
    <form method="get" action="/ausgaben" class="search" style="display:flex; gap:8px;">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ausgaben suchen…">
        <input type="number" name="jahr" value="<?= e($jahr) ?>" placeholder="Jahr" style="width:90px;">
    </form>
    <?php if (can('manage_expenses')): ?><a href="/ausgaben/neu" class="btn">+ Neue Ausgabe</a><?php endif; ?>
</div>

<?php if ($expenses === []): ?>
    <div class="table-wrap"><div class="empty">Keine Ausgaben gefunden.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Datum</th><th>Lieferant</th><th>EÜR-Kategorie</th><th>Status</th><th class="num">Betrag</th></tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $ex): ?>
            <tr onclick="location.href='/ausgaben/<?= (int) $ex['id'] ?>'" style="cursor:pointer">
                <td><?= dt($ex['expense_date']) ?></td>
                <td><strong><?= e($ex['supplier'] ?: '—') ?></strong></td>
                <td><?= e($ex['tax_category'] ?: $ex['category']) ?></td>
                <td><span class="badge badge-<?= $ex['status'] === 'paid' ? 'paid' : 'overdue' ?>"><?= $ex['status'] === 'paid' ? 'Bezahlt' : 'Offen' ?></span></td>
                <td class="num"><?= money((int) $ex['amount_cents']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="4">Summe</th><th class="num"><?= money($sum) ?></th></tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
