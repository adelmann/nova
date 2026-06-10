<?php
/** @var array<int,array<string,mixed>> $vendors */
/** @var string $q */
/** @var bool $showArchived */
$showArchived = $showArchived ?? false;
?>
<div class="toolbar">
    <form method="get" action="/lieferanten" class="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Lieferanten suchen…" data-table-filter="vendor-table">
        <?php if ($showArchived): ?><input type="hidden" name="archiv" value="1"><?php endif; ?>
    </form>
    <div style="display:flex; gap:8px;">
        <?php if ($showArchived): ?>
            <a href="/lieferanten" class="btn btn-secondary">Archivierte ausblenden</a>
        <?php else: ?>
            <a href="/lieferanten?archiv=1" class="btn btn-secondary">Archivierte anzeigen</a>
        <?php endif; ?>
        <a href="/lieferanten/neu" class="btn">+ Neuer Lieferant</a>
    </div>
</div>

<?php if ($vendors === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Lieferanten erfasst.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table id="vendor-table">
        <thead>
            <tr><th>Name</th><th>Ansprechpartner</th><th>Ort</th><th>E-Mail</th></tr>
        </thead>
        <tbody>
        <?php foreach ($vendors as $v): ?>
            <tr onclick="location.href='/lieferanten/<?= (int) $v['id'] ?>'" style="cursor:pointer">
                <td><strong><?= e($v['name']) ?></strong>
                    <?php if (!empty($v['archived_at'])): ?> <span class="badge badge-draft">archiviert</span><?php endif; ?>
                </td>
                <td><?= e($v['contact_name']) ?></td>
                <td><?= e($v['city']) ?></td>
                <td><?= e($v['email']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
