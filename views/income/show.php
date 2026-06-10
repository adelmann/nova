<?php
/** @var array<string,mixed> $income */
$in = $income;
?>
<div class="toolbar">
    <a href="/einnahmen" class="btn btn-secondary btn-sm">← Zurück</a>
    <div style="display:flex; gap:10px;">
        <a href="/einnahmen/<?= (int) $in['id'] ?>/bearbeiten" class="btn btn-sm">Bearbeiten</a>
        <form method="post" action="/einnahmen/<?= (int) $in['id'] ?>/loeschen" data-confirm="Einnahme wirklich löschen? (Journal wird per Gegenbuchung ausgeglichen)">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
        </form>
    </div>
</div>

<div class="panel">
    <dl class="detail">
        <dt>Datum</dt><dd><?= dt($in['income_date']) ?></dd>
        <dt>Quelle</dt><dd><?= e($in['source']) ?: '–' ?></dd>
        <dt>Kategorie</dt><dd><?= e($in['category']) ?></dd>
        <dt>Betrag</dt><dd><strong class="pos"><?= money((int) $in['amount_cents']) ?></strong></dd>
        <dt>Projekt</dt><dd><?php if (!empty($in['project_id'])): ?><a href="/projekte/<?= (int) $in['project_id'] ?>"><?= e($in['project_name'] ?? ('Projekt #' . $in['project_id'])) ?></a><?php else: ?>–<?php endif; ?></dd>
        <?php if (!empty($in['note'])): ?><dt>Notiz</dt><dd><?= nl2br(e($in['note'])) ?></dd><?php endif; ?>
    </dl>
</div>
<p class="help">Diese Einnahme ist im Buchungsjournal verbucht und in der EÜR/im Dashboard enthalten.</p>
