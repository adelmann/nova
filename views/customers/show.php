<?php
/** @var array<string,mixed> $customer */
$c = $customer;
?>
<div class="toolbar">
    <a href="/kunden" class="btn btn-secondary btn-sm">← Zurück</a>
    <div style="display:flex; gap:10px;">
        <?php if (can('view_accounting')): ?><a href="/offene-posten/kunde/<?= (int) $c['id'] ?>" class="btn btn-secondary btn-sm">Kontoauszug</a><?php endif; ?>
        <a href="/kunden/<?= (int) $c['id'] ?>/bearbeiten" class="btn btn-sm">Bearbeiten</a>
        <?php if (empty($c['archived_at'])): ?>
            <form method="post" action="/kunden/<?= (int) $c['id'] ?>/archivieren" data-confirm="Kunde archivieren? Er wird aus Listen und Auswahlfeldern ausgeblendet, die Historie bleibt erhalten.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-sm">Archivieren</button>
            </form>
        <?php else: ?>
            <form method="post" action="/kunden/<?= (int) $c['id'] ?>/wiederherstellen">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm">Wiederherstellen</button>
            </form>
        <?php endif; ?>
        <form method="post" action="/kunden/<?= (int) $c['id'] ?>/loeschen" data-confirm="Kunde wirklich löschen?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger btn-sm">Löschen</button>
        </form>
    </div>
</div>

<?php if (!empty($c['archived_at'])): ?>
    <div class="flash flash-warn">Dieser Kunde ist archiviert (seit <?= dt($c['archived_at']) ?>) und in Auswahlfeldern ausgeblendet.</div>
<?php endif; ?>

<div class="panel">
    <dl class="detail">
        <dt>Firma</dt><dd><?= e($c['company_name']) ?: '–' ?></dd>
        <dt>Ansprechpartner</dt><dd><?= e($c['contact_name']) ?: '–' ?></dd>
        <dt>Typ</dt><dd>
            <span class="badge badge-<?= e($c['type']) ?>"><?= $c['type'] === 'private' ? 'Privat' : 'Geschäft' ?></span>
        </dd>
        <dt>Adresse</dt><dd>
            <?= e($c['address_line1']) ?><?= $c['address_line2'] ? '<br>' . e($c['address_line2']) : '' ?>
            <?php if ($c['zip'] || $c['city']): ?><br><?= e(trim($c['zip'] . ' ' . $c['city'])) ?><?php endif; ?>
            <?= $c['country'] ? '<br>' . e($c['country']) : '' ?>
        </dd>
        <dt>E-Mail</dt><dd><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '–' ?></dd>
        <dt>Telefon</dt><dd><?= e($c['phone']) ?: '–' ?></dd>
        <dt>USt-ID</dt><dd><?= e($c['vat_id']) ?: '–' ?></dd>
        <?php if (!empty($c['notes'])): ?>
            <dt>Notizen</dt><dd><?= nl2br(e($c['notes'])) ?></dd>
        <?php endif; ?>
    </dl>
</div>
