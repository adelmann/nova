<?php
/** @var array<int,array<string,mixed>> $profiles */
$units = ['month' => 'monatlich', 'quarter' => 'quartalsweise', 'year' => 'jährlich'];
?>
<div class="toolbar">
    <h2 style="margin:0; font-size:16px;">Wiederkehrende Rechnungen</h2>
    <a href="/wiederkehrend/neu" class="btn">+ Neue wiederkehrende Rechnung</a>
</div>

<?php if ($profiles === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine wiederkehrenden Rechnungen. Lege ein Profil an (Kunde + Positionen + Intervall) – Nova erzeugt die Rechnungen dann automatisch zum Fälligkeitstag.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Bezeichnung</th><th>Kunde</th><th>Intervall</th><th>Nächste am</th><th>Versand</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($profiles as $p): ?>
            <tr onclick="location.href='/wiederkehrend/<?= (int) $p['id'] ?>/bearbeiten'" style="cursor:pointer">
                <td><strong><?= e($p['title'] ?: 'Ohne Bezeichnung') ?></strong></td>
                <td><?= e($p['company_name'] ?: $p['contact_name']) ?></td>
                <td><?= e($units[$p['interval_unit']] ?? $p['interval_unit']) ?></td>
                <td><?= dt($p['next_date']) ?></td>
                <td><?= (int) $p['auto_send'] === 1 ? 'auto. finalisieren + senden' : 'als Entwurf' ?></td>
                <td><?php if ((int) $p['active'] === 1): ?><span class="badge badge-paid">aktiv</span><?php else: ?><span class="badge badge-draft">pausiert</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
