<?php
/** @var array<int,array<string,mixed>> $groups */
/** @var array<string,int> $buckets */
/** @var int $total */
/** @var int $count */
?>
<div class="cards">
    <div class="card"><div class="label">Offene Forderungen</div><div class="value"><?= money($total) ?></div><div class="sub"><?= (int) $count ?> Rechnung(en)</div></div>
    <div class="card"><div class="label">Nicht fällig</div><div class="value"><?= money($buckets['not_due']) ?></div></div>
    <div class="card"><div class="label">1–30 Tage überfällig</div><div class="value"><?= money($buckets['d1_30']) ?></div></div>
    <div class="card"><div class="label">31–60 Tage</div><div class="value"><?= money($buckets['d31_60']) ?></div></div>
    <div class="card"><div class="label">über 60 Tage</div><div class="value <?= $buckets['d60p'] > 0 ? 'neg' : '' ?>"><?= money($buckets['d60p']) ?></div></div>
</div>

<?php if ($groups === []): ?>
    <div class="table-wrap"><div class="empty">Keine offenen Posten – alle finalisierten Rechnungen sind bezahlt. 🎉</div></div>
<?php else: ?>
    <?php foreach ($groups as $g): ?>
        <div class="panel">
            <div class="toolbar" style="margin-top:0;">
                <h2 style="margin:0; font-size:15px;"><?= e($g['name']) ?> · <span class="muted">offen <?= money((int) $g['open']) ?></span></h2>
                <a href="/offene-posten/kunde/<?= (int) $g['customer_id'] ?>" class="btn btn-secondary btn-sm">Kontoauszug</a>
            </div>
            <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
                <table>
                    <thead><tr><th>Rechnung</th><th>Datum</th><th>Fällig</th><th>Überfällig</th><th class="num">Brutto</th><th class="num">Bezahlt</th><th class="num">Offen</th></tr></thead>
                    <tbody>
                    <?php foreach ($g['items'] as $r): ?>
                        <tr onclick="location.href='/rechnungen/<?= (int) $r['id'] ?>'" style="cursor:pointer">
                            <td><strong><?= e($r['number']) ?></strong></td>
                            <td><?= dt($r['invoice_date']) ?></td>
                            <td><?= $r['due_date'] ? dt($r['due_date']) : '–' ?></td>
                            <td><?php if ((int) $r['days_overdue'] > 0): ?><span class="badge badge-overdue"><?= (int) $r['days_overdue'] ?> Tage</span><?php else: ?><span class="muted">–</span><?php endif; ?></td>
                            <td class="num"><?= money((int) $r['gross_total_cents']) ?></td>
                            <td class="num"><?= money((int) $r['paid_total_cents']) ?></td>
                            <td class="num"><strong><?= money((int) $r['open_cents']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
