<?php
/** @var array<string,mixed> $vendor */
$v = $vendor;
?>
<div class="toolbar">
    <a href="/lieferanten" class="btn btn-secondary btn-sm">← Zurück</a>
    <div style="display:flex; gap:10px;">
        <a href="/lieferanten/<?= (int) $v['id'] ?>/bearbeiten" class="btn btn-sm">Bearbeiten</a>
        <?php if (empty($v['archived_at'])): ?>
            <form method="post" action="/lieferanten/<?= (int) $v['id'] ?>/archivieren" data-confirm="Lieferant archivieren? Er wird aus der Auswahl ausgeblendet.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Archivieren</button>
            </form>
        <?php else: ?>
            <form method="post" action="/lieferanten/<?= (int) $v['id'] ?>/wiederherstellen">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm">Wiederherstellen</button>
            </form>
        <?php endif; ?>
        <form method="post" action="/lieferanten/<?= (int) $v['id'] ?>/loeschen" data-confirm="Lieferant wirklich löschen?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
        </form>
    </div>
</div>

<?php if (!empty($v['archived_at'])): ?>
    <div class="flash flash-warn">Dieser Lieferant ist archiviert (seit <?= dt($v['archived_at']) ?>) und in der Auswahl ausgeblendet.</div>
<?php endif; ?>

<div class="panel">
    <dl class="detail">
        <dt>Name</dt><dd><strong><?= e($v['name']) ?></strong></dd>
        <?php if ($v['contact_name']): ?><dt>Ansprechpartner</dt><dd><?= e($v['contact_name']) ?></dd><?php endif; ?>
        <?php if ($v['email']): ?><dt>E-Mail</dt><dd><?= e($v['email']) ?></dd><?php endif; ?>
        <?php if ($v['phone']): ?><dt>Telefon</dt><dd><?= e($v['phone']) ?></dd><?php endif; ?>
        <?php if ($v['website']): ?><dt>Website</dt><dd><?= e($v['website']) ?></dd><?php endif; ?>
        <?php if ($v['address_line1'] || $v['city']): ?><dt>Adresse</dt><dd><?= e($v['address_line1']) ?><?= $v['address_line1'] ? ', ' : '' ?><?= e(trim($v['zip'] . ' ' . $v['city'])) ?></dd><?php endif; ?>
        <?php if ($v['vat_id']): ?><dt>USt-ID</dt><dd><?= e($v['vat_id']) ?></dd><?php endif; ?>
        <?php if ($v['note']): ?><dt>Notiz</dt><dd><?= nl2br(e($v['note'])) ?></dd><?php endif; ?>
    </dl>
</div>
