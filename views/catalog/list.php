<?php
/** @var array<int,array<string,mixed>> $items */
?>
<div class="toolbar">
    <h2 style="margin:0; font-size:16px;">Leistungskatalog</h2>
    <a href="/katalog/neu" class="btn">+ Neue Position</a>
</div>

<?php if ($items === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Katalog-Positionen. Lege wiederkehrende Leistungen/Artikel an, um sie in Angeboten und Rechnungen schnell einzufügen.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Bezeichnung</th><th>Einheit</th><th class="num">Einzelpreis</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr onclick="location.href='/katalog/<?= (int) $it['id'] ?>/bearbeiten'" style="cursor:pointer">
                <td><strong><?= e($it['name']) ?></strong></td>
                <td><?= e($it['unit']) ?></td>
                <td class="num"><?= money((int) $it['unit_price_cents']) ?></td>
                <td style="text-align:right">
                    <form method="post" action="/katalog/<?= (int) $it['id'] ?>/loeschen" data-confirm="Position löschen?" style="display:inline" onclick="event.stopPropagation()">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
