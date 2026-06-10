<?php
/** @var array<string,mixed> $asset */
/** @var array<int,int> $schedule */
/** @var array<int,int> $booked */
/** @var int $currentYear */
$a = $asset;
$methods = ['linear' => 'Linear', 'gwg' => 'GWG (Sofortabschreibung)'];
$written = 0;
?>
<div class="panel">
    <div class="toolbar" style="margin-top:0;">
        <h2 style="margin:0;"><?= e($a['name'] ?: 'Anlagegut') ?></h2>
        <?php if (can('manage_expenses')): ?><a href="/anlagen/<?= (int) $a['id'] ?>/bearbeiten" class="btn btn-secondary">Bearbeiten</a><?php endif; ?>
    </div>
    <dl class="detail">
        <dt>Anschaffung</dt><dd><?= dt($a['acquired_date']) ?> · <?= money((int) $a['cost_cents']) ?></dd>
        <?php if ($a['supplier']): ?><dt>Lieferant</dt><dd><?= e($a['supplier']) ?></dd><?php endif; ?>
        <dt>Methode</dt><dd><?= e($methods[$a['method']] ?? $a['method']) ?><?= (string) $a['method'] === 'linear' ? ' · ' . (int) $a['useful_life_years'] . ' Jahre Nutzungsdauer' : '' ?></dd>
        <?php if ($a['note']): ?><dt>Notiz</dt><dd><?= e($a['note']) ?></dd><?php endif; ?>
    </dl>
</div>

<div class="panel">
    <h2>AfA-Plan</h2>
    <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
        <table>
            <thead><tr><th>Jahr</th><th class="num">AfA</th><th class="num">Restbuchwert</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($schedule as $year => $amount): $written += $amount; $rest = (int) $a['cost_cents'] - $written; ?>
                <tr>
                    <td><?= e((string) $year) ?></td>
                    <td class="num"><?= money($amount) ?></td>
                    <td class="num"><?= money(max(0, $rest)) ?></td>
                    <td>
                        <?php if (isset($booked[$year])): ?>
                            <span class="badge badge-paid">gebucht</span>
                        <?php elseif ($year < $currentYear): ?>
                            <span class="badge badge-overdue">offen (fällig)</span>
                        <?php else: ?>
                            <span class="badge badge-draft">geplant</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="help">Die AfA wird automatisch zum Jahresende (31.12.) gebucht, sobald das Jahr abgeschlossen ist. Bereits gebuchte Jahre bleiben unveränderbar (GoBD).</p>
</div>

<?php if (can('manage_expenses')): ?>
<form method="post" action="/anlagen/<?= (int) $a['id'] ?>/loeschen" onsubmit="return confirm('Anlagegut wirklich löschen?');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-danger">Löschen</button>
</form>
<?php endif; ?>
