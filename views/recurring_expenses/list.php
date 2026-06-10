<?php
/** @var array<int,array<string,mixed>> $profiles */
$units = ['month' => 'monatlich', 'quarter' => 'quartalsweise', 'year' => 'jährlich'];
?>
<div class="toolbar">
    <h2 style="margin:0; font-size:16px;">Dauerausgaben</h2>
    <a href="/dauerausgaben/neu" class="btn">+ Neue Dauerausgabe</a>
</div>

<?php if ($profiles === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Dauerausgaben. Lege ein Profil an (z.&nbsp;B. Miete, Software-Abo, Versicherung) – Nova bucht die Ausgabe dann automatisch zum Fälligkeitstag ins Journal.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Bezeichnung</th><th>Lieferant</th><th>EÜR-Kategorie</th><th>Intervall</th><th>Nächste am</th><th class="num">Betrag</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($profiles as $p): ?>
            <tr onclick="location.href='/dauerausgaben/<?= (int) $p['id'] ?>/bearbeiten'" style="cursor:pointer">
                <td><strong><?= e($p['title'] ?: 'Ohne Bezeichnung') ?></strong></td>
                <td><?= e($p['supplier']) ?></td>
                <td><?= e($p['tax_category'] ?: $p['category']) ?></td>
                <td><?= e($units[$p['interval_unit']] ?? $p['interval_unit']) ?></td>
                <td><?= dt($p['next_date']) ?></td>
                <td class="num"><?= money((int) $p['amount_cents']) ?></td>
                <td><?php if ((int) $p['active'] === 1): ?><span class="badge badge-paid">aktiv</span><?php else: ?><span class="badge badge-draft">pausiert</span><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<p class="help">Dauerausgaben werden beim Cron-Lauf (<code>bin/sweep.php</code>) zum jeweiligen Fälligkeitstag als bezahlte Ausgabe gebucht und in die EÜR übernommen. Anschließend rückt das Fälligkeitsdatum automatisch ein Intervall weiter.</p>
