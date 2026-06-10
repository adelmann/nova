<?php
/** @var array<int,array<string,mixed>> $incomes */
/** @var string $q */
/** @var int|null $jahr */
/** @var int $sum */
?>
<div class="toolbar">
    <form method="get" action="/einnahmen" class="search" style="display:flex; gap:8px;">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Einnahmen suchen…">
        <input type="number" name="jahr" value="<?= e($jahr) ?>" placeholder="Jahr" style="width:90px;">
    </form>
    <a href="/einnahmen/neu" class="btn">+ Neue Einnahme</a>
</div>

<p class="help">Direkte Einnahmen ohne Rechnung (z.B. Affiliate-Erlöse). Sie fließen automatisch ins Buchungsjournal und in die EÜR. Rechnungseinnahmen werden weiterhin über die Rechnungen + Zahlungseingänge erfasst.</p>

<?php if ($incomes === []): ?>
    <div class="table-wrap"><div class="empty">Keine direkten Einnahmen erfasst.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Datum</th><th>Quelle</th><th>Kategorie</th><th>Projekt</th><th class="num">Betrag</th></tr></thead>
        <tbody>
        <?php foreach ($incomes as $in): ?>
            <tr onclick="location.href='/einnahmen/<?= (int) $in['id'] ?>'" style="cursor:pointer">
                <td><?= dt($in['income_date']) ?></td>
                <td><strong><?= e($in['source'] ?: '—') ?></strong></td>
                <td><?= e($in['category']) ?></td>
                <td><?= e($in['project_name'] ?? '') ?: '–' ?></td>
                <td class="num pos"><?= money((int) $in['amount_cents']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="4">Summe</th><th class="num"><?= money($sum) ?></th></tr></tfoot>
    </table>
</div>
<?php endif; ?>
