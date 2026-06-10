<?php
/** @var array<int,array<string,mixed>> $customers */
/** @var string $q */
/** @var bool $showArchived */
$showArchived = $showArchived ?? false;
?>
<div class="toolbar">
    <form method="get" action="/kunden" class="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Kunden suchen…" data-table-filter="customer-table">
        <?php if ($showArchived): ?><input type="hidden" name="archiv" value="1"><?php endif; ?>
    </form>
    <div style="display:flex; gap:8px;">
        <?php if ($showArchived): ?>
            <a href="/kunden" class="btn btn-secondary">Archivierte ausblenden</a>
        <?php else: ?>
            <a href="/kunden?archiv=1" class="btn btn-secondary">Archivierte anzeigen</a>
        <?php endif; ?>
        <a href="/kunden/neu" class="btn">+ Neuer Kunde</a>
    </div>
</div>

<?php if ($customers === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Kunden erfasst.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table id="customer-table">
        <thead>
            <tr>
                <th>Firma / Name</th>
                <th>Ansprechpartner</th>
                <th>Ort</th>
                <th>E-Mail</th>
                <th>Typ</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
            <tr onclick="location.href='/kunden/<?= (int) $c['id'] ?>'" style="cursor:pointer">
                <td><strong><?= e($c['company_name'] ?: $c['contact_name']) ?></strong>
                    <?php if (!empty($c['archived_at'])): ?> <span class="badge badge-draft">archiviert</span><?php endif; ?>
                </td>
                <td><?= e($c['company_name'] ? $c['contact_name'] : '') ?></td>
                <td><?= e($c['city']) ?></td>
                <td><?= e($c['email']) ?></td>
                <td>
                    <span class="badge badge-<?= e($c['type']) ?>">
                        <?= $c['type'] === 'private' ? 'Privat' : 'Geschäft' ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
