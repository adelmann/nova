<?php
/** @var array<int,array<string,mixed>> $receipts */
/** @var array<int,array<string,mixed>> $expenses */
/** @var array<int,array<string,mixed>> $invoices */
/** @var string $q */
$types = ['eingangsrechnung' => 'Eingangsrechnung', 'quittung' => 'Quittung', 'kontoauszug' => 'Kontoauszug', 'sonstiges' => 'Sonstiges'];
$expenses = $expenses ?? [];
$invoices = $invoices ?? [];
?>
<div class="toolbar">
    <form method="get" action="/belege" class="search">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Belege suchen…">
    </form>
    <div style="display:flex; gap:8px;">
        <a href="/exporte/belege" class="btn btn-secondary">Jahres-ZIP</a>
        <?php if (can('manage_expenses')): ?><a href="/belege/neu" class="btn">+ Beleg hochladen</a><?php endif; ?>
    </div>
</div>

<?php if ($receipts === []): ?>
    <div class="table-wrap"><div class="empty">Noch keine Belege.</div></div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Datei</th><th>Typ</th><th>Zuordnung</th><th>Hochgeladen</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($receipts as $r): ?>
            <tr>
                <td><a href="/belege/<?= (int) $r['id'] ?>/download" target="_blank">📎 <?= e($r['original_name']) ?></a></td>
                <td><?= e($types[$r['type']] ?? $r['type']) ?></td>
                <td>
                    <?php if ($r['linkable_type'] === 'expense'): ?>
                        <a href="/ausgaben/<?= (int) $r['linkable_id'] ?>">Ausgabe #<?= (int) $r['linkable_id'] ?></a>
                    <?php elseif ($r['linkable_type'] === 'invoice'): ?>
                        <a href="/rechnungen/<?= (int) $r['linkable_id'] ?>">Rechnung #<?= (int) $r['linkable_id'] ?></a>
                    <?php elseif (!can('manage_expenses')): ?>
                        <span class="muted">nicht zugeordnet</span>
                    <?php else: ?>
                        <form method="post" action="/belege/<?= (int) $r['id'] ?>/zuordnen" style="display:flex; gap:6px; align-items:center;">
                            <?= csrf_field() ?>
                            <select name="target" style="width:auto; max-width:230px;">
                                <option value="">– zuordnen zu… –</option>
                                <?php if ($invoices !== []): ?>
                                    <optgroup label="Rechnungen">
                                        <?php foreach ($invoices as $inv): ?>
                                            <option value="invoice:<?= (int) $inv['id'] ?>">
                                                <?= e($inv['number'] ?: 'Entwurf #' . $inv['id']) ?> · <?= e($inv['company_name'] ?: $inv['contact_name']) ?> · <?= money((int) $inv['gross_total_cents']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ($expenses !== []): ?>
                                    <optgroup label="Ausgaben">
                                        <?php foreach ($expenses as $ex): ?>
                                            <option value="expense:<?= (int) $ex['id'] ?>">
                                                <?= dt($ex['expense_date']) ?> · <?= e($ex['supplier'] ?: 'Ausgabe #' . $ex['id']) ?> · <?= money((int) $ex['amount_cents']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <button type="submit" class="btn btn-secondary btn-sm">OK</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td><?= dt($r['created_at']) ?></td>
                <td style="text-align:right">
                    <?php if ((int) $r['locked'] === 0 && can('manage_expenses')): ?>
                        <form method="post" action="/belege/<?= (int) $r['id'] ?>/loeschen" data-confirm="Beleg löschen?" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-secondary btn-sm">✕</button>
                        </form>
                    <?php elseif ((int) $r['locked'] === 1): ?><span class="muted" title="archiviert">🔒</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
