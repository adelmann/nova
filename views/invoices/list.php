<?php
/** @var array<int,array<string,mixed>> $invoices */
$labels = ['draft' => 'Entwurf', 'sent' => 'Versendet', 'paid' => 'Bezahlt', 'overdue' => 'Überfällig', 'cancelled' => 'Storniert'];
?>
<div class="toolbar">
    <input type="search" placeholder="Rechnungen filtern…" data-table-filter="invoice-table" class="search">
    <a href="/rechnungen/neu" class="btn">+ Neue Rechnung</a>
</div>

<?php if ($invoices === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Rechnungen.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table id="invoice-table">
        <thead>
            <tr><th>Nummer</th><th>Datum</th><th>Kunde</th><th>Status</th><th class="num">Betrag</th></tr>
        </thead>
        <tbody>
        <?php foreach ($invoices as $i): ?>
            <tr onclick="location.href='/rechnungen/<?= (int) $i['id'] ?>'" style="cursor:pointer">
                <td><strong><?= e($i['number'] ?: '—') ?></strong></td>
                <td><?= dt($i['invoice_date']) ?></td>
                <td><?= e($i['company_name'] ?: $i['contact_name']) ?></td>
                <td><span class="badge badge-<?= e($i['status']) ?>"><?= e($labels[$i['status']] ?? $i['status']) ?></span></td>
                <td class="num"><?= money((int) $i['gross_total_cents']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
