<?php
/** @var array<int,array<string,mixed>> $quotes */
$labels = ['draft' => 'Entwurf', 'sent' => 'Versendet', 'accepted' => 'Angenommen', 'rejected' => 'Abgelehnt'];
$badge  = ['draft' => 'draft', 'sent' => 'sent', 'accepted' => 'paid', 'rejected' => 'cancelled'];
?>
<div class="toolbar">
    <input type="search" placeholder="Angebote filtern…" data-table-filter="quote-table" class="search">
    <a href="/angebote/neu" class="btn">+ Neues Angebot</a>
</div>

<?php if ($quotes === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Angebote.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table id="quote-table">
        <thead>
            <tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Status</th><th class="num">Betrag</th></tr>
        </thead>
        <tbody>
        <?php foreach ($quotes as $q): ?>
            <tr onclick="location.href='/angebote/<?= (int) $q['id'] ?>'" style="cursor:pointer">
                <td><strong><?= e($q['number'] ?: '—') ?></strong></td>
                <td><?= dt($q['quote_date']) ?></td>
                <td><?= e($q['company_name'] ?: $q['contact_name']) ?></td>
                <td><span class="badge badge-<?= $badge[$q['status']] ?>"><?= e($labels[$q['status']]) ?></span></td>
                <td class="num"><?= money((int) $q['gross_total_cents']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
