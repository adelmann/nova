<?php
/** @var array<string,mixed> $expense */
/** @var array<int,array<string,mixed>> $receipts */
$ex = $expense;
?>
<div class="toolbar">
    <a href="/ausgaben" class="btn btn-secondary btn-sm">← Zurück</a>
    <?php if (can('manage_expenses')): ?>
    <div style="display:flex; gap:10px;">
        <a href="/ausgaben/<?= (int) $ex['id'] ?>/bearbeiten" class="btn btn-sm">Bearbeiten</a>
        <form method="post" action="/ausgaben/<?= (int) $ex['id'] ?>/loeschen" data-confirm="Ausgabe wirklich löschen? (Journal wird per Gegenbuchung ausgeglichen)">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="panel">
    <dl class="detail">
        <dt>Datum</dt><dd><?= dt($ex['expense_date']) ?></dd>
        <dt>Lieferant</dt><dd><?= e($ex['supplier']) ?: '–' ?></dd>
        <dt>EÜR-Kategorie</dt><dd><?= e($ex['tax_category'] ?: $ex['category']) ?: '–' ?></dd>
        <dt>Betrag</dt><dd><strong><?= money((int) $ex['amount_cents']) ?></strong></dd>
        <dt>Zahlungsart</dt><dd><?= e($ex['method']) ?: '–' ?></dd>
        <dt>Status</dt><dd><span class="badge badge-<?= $ex['status'] === 'paid' ? 'paid' : 'overdue' ?>"><?= $ex['status'] === 'paid' ? 'Bezahlt' : 'Offen' ?></span></dd>
        <?php if (!empty($ex['note'])): ?><dt>Notiz</dt><dd><?= nl2br(e($ex['note'])) ?></dd><?php endif; ?>
        <dt>Belege</dt><dd>
            <?php if ($receipts === []): ?>–<?php else: ?>
                <?php foreach ($receipts as $r): ?>
                    <a href="/belege/<?= (int) $r['id'] ?>/download" target="_blank">📎 <?= e($r['original_name']) ?></a><br>
                <?php endforeach; ?>
            <?php endif; ?>
        </dd>
    </dl>
</div>
