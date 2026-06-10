<?php
/** @var array<int,array<string,mixed>> $projects */
$labels = ['active' => 'Aktiv', 'paused' => 'Pausiert', 'done' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'];
?>
<div class="toolbar">
    <input type="search" placeholder="Projekte filtern…" data-table-filter="project-table" class="search">
    <a href="/projekte/neu" class="btn">+ Neues Projekt</a>
</div>

<?php if ($projects === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Projekte erfasst.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table id="project-table">
        <thead>
            <tr><th>Projekt</th><th>Kunde</th><th>Status</th><th class="num">Stundensatz</th></tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
            <tr onclick="location.href='/projekte/<?= (int) $p['id'] ?>'" style="cursor:pointer">
                <td><strong><?= e($p['name']) ?></strong></td>
                <td>
                    <?php if (($p['project_type'] ?? 'customer') === 'internal'): ?>
                        <span class="badge badge-business">Intern</span>
                    <?php else: ?>
                        <?= e($p['company_name'] ?: $p['contact_name']) ?: '–' ?>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $p['status'] === 'active' ? 'paid' : 'draft' ?>"><?= e($labels[$p['status']] ?? $p['status']) ?></span></td>
                <td class="num"><?= money((int) $p['hourly_rate_cents']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
