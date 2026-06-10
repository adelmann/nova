<?php
/** @var array<int,array<string,mixed>> $assets */
/** @var int $year */
/** @var int $totalCost */
/** @var int $totalBook */
$methods = ['linear' => 'linear', 'gwg' => 'GWG (sofort)'];
?>
<div class="toolbar">
    <h2 style="margin:0; font-size:16px;">Anlagevermögen</h2>
    <?php if (can('manage_expenses')): ?><a href="/anlagen/neu" class="btn">+ Neues Anlagegut</a><?php endif; ?>
</div>

<?php if ($assets === []): ?>
    <div class="table-wrap"><div class="empty">Noch kein Anlagevermögen erfasst. Hier kommen abnutzbare Wirtschaftsgüter (z.&nbsp;B. Notebook, Maschine, Büromöbel) hinein. Nova berechnet die jährliche Abschreibung (AfA) und bucht sie automatisch in die EÜR.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Bezeichnung</th><th>Angeschafft</th><th>Methode</th><th>Nutzungsdauer</th><th class="num">Anschaffung</th><th class="num">Restwert <?= e((string) $year) ?></th></tr></thead>
        <tbody>
        <?php foreach ($assets as $a): ?>
            <tr onclick="location.href='/anlagen/<?= (int) $a['id'] ?>'" style="cursor:pointer">
                <td><strong><?= e($a['name'] ?: 'Ohne Bezeichnung') ?></strong><?= $a['supplier'] ? ' · <span class="muted">' . e($a['supplier']) . '</span>' : '' ?></td>
                <td><?= dt($a['acquired_date']) ?></td>
                <td><?= e($methods[$a['method']] ?? $a['method']) ?></td>
                <td><?= (string) $a['method'] === 'gwg' ? '–' : ((int) $a['useful_life_years'] . ' Jahre') ?></td>
                <td class="num"><?= money((int) $a['cost_cents']) ?></td>
                <td class="num"><?= money((int) $a['book_value']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th colspan="4">Summe</th><th class="num"><?= money($totalCost) ?></th><th class="num"><?= money($totalBook) ?></th></tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>
<p class="help">Der Kaufpreis eines Anlageguts ist nicht sofort abzugsfähig – abgeschrieben wird über die Nutzungsdauer (AfA). Geringwertige Wirtschaftsgüter (GWG, bis 800&nbsp;€ netto) werden im Anschaffungsjahr voll abgeschrieben. Die AfA wird zum Jahresende automatisch gebucht.</p>
