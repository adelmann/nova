<?php
/** @var array<string,mixed> $customer */
/** @var array<int,array<string,mixed>> $events */
/** @var int $balance */
$c = $customer;
?>
<div class="toolbar">
    <a href="/offene-posten" class="btn btn-secondary btn-sm">← Offene Posten</a>
    <a href="/kunden/<?= (int) $c['id'] ?>" class="btn btn-secondary btn-sm">Kundenakte</a>
</div>

<div class="panel">
    <h2 style="margin-top:0;">Kontoauszug · <?= e($c['company_name'] ?: $c['contact_name']) ?></h2>
    <?php if ($events === []): ?>
        <div class="empty">Für diesen Kunden gibt es noch keine finalisierten Rechnungen.</div>
    <?php else: ?>
    <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
        <table>
            <thead><tr><th>Datum</th><th>Vorgang</th><th>Beleg</th><th class="num">Betrag</th><th class="num">Saldo</th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev): ?>
                <tr>
                    <td><?= dt($ev['date']) ?></td>
                    <td><?= e($ev['type']) ?></td>
                    <td><?= e($ev['ref']) ?></td>
                    <td class="num" style="<?= $ev['amount'] < 0 ? 'color:var(--success)' : '' ?>"><?= money((int) $ev['amount']) ?></td>
                    <td class="num"><?= money((int) $ev['balance']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="4">Offener Saldo</th><th class="num"><?= money($balance) ?></th></tr>
            </tfoot>
        </table>
    </div>
    <p class="help">Positive Beträge sind Forderungen (Rechnungen), negative sind Zahlungseingänge. Der Saldo zeigt den jeweils offenen Betrag; ein Saldo von 0&nbsp;€ bedeutet vollständig ausgeglichen.</p>
    <?php endif; ?>
</div>
