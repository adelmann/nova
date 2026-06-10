<?php
/** @var array<int,array<string,mixed>> $entries */
/** @var array<int,string> $types */
/** @var string $type */
/** @var int $page */
/** @var int $perPage */
/** @var int $total */
$actionLabel = ['create' => 'Angelegt', 'update' => 'Geändert', 'delete' => 'Gelöscht', 'finalize' => 'Finalisiert', 'cancel' => 'Storniert', 'payment' => 'Zahlung', 'login' => 'Login'];
$pages = (int) ceil($total / $perPage);
?>
<div class="toolbar">
    <form method="get" action="/protokoll" style="display:flex; gap:8px; align-items:center;">
        <label class="muted" for="typ" style="font-size:13px;">Bereich</label>
        <select name="typ" id="typ" style="width:auto;" onchange="this.form.submit()">
            <option value="">Alle</option>
            <?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $t === $type ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select>
    </form>
    <span class="muted"><?= $total ?> Einträge</span>
</div>

<p class="help">Das Änderungsprotokoll ist unveränderbar (GoBD) und erfasst alle wesentlichen Aktionen.</p>

<?php if ($entries === []): ?>
    <div class="table-wrap"><div class="empty">Keine Protokolleinträge.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Zeitpunkt</th><th>Benutzer</th><th>Aktion</th><th>Objekt</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($entries as $e): ?>
            <tr>
                <td style="white-space:nowrap"><?= e(date('d.m.Y H:i', strtotime($e['occurred_at']))) ?></td>
                <td><?= e($e['user_label']) ?></td>
                <td><span class="badge badge-draft"><?= e($actionLabel[$e['action']] ?? $e['action']) ?></span></td>
                <td><?= e($e['entity_type']) ?><?= $e['entity_id'] ? '#' . (int) $e['entity_id'] : '' ?></td>
                <td><code style="font-size:11px; color:var(--muted)"><?= e(mb_strimwidth((string) $e['diff_json'], 0, 90, '…')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($pages > 1): ?>
    <div style="margin-top:14px; display:flex; gap:8px;">
        <?php if ($page > 1): ?><a class="btn btn-secondary btn-sm" href="/protokoll?typ=<?= e($type) ?>&seite=<?= $page - 1 ?>">← Zurück</a><?php endif; ?>
        <span class="muted" style="align-self:center">Seite <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-secondary btn-sm" href="/protokoll?typ=<?= e($type) ?>&seite=<?= $page + 1 ?>">Weiter →</a><?php endif; ?>
    </div>
<?php endif; ?>
<?php endif; ?>
