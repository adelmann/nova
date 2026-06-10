<?php
/** @var array<int,array{date:string,amount:int,purpose:string,match_id:int,match_label:string}> $rows */
?>
<div class="panel">
    <h2>Vorschau – Ausgaben übernehmen &amp; Zahlungen zuordnen</h2>
    <form method="post" action="/bankimport/buchen">
        <?= csrf_field() ?>
        <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
            <table>
                <thead><tr><th>Übernehmen</th><th>Datum</th><th>Verwendungszweck</th><th class="num">Betrag</th><th>Aktion</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <?php $isExpense = $r['amount'] < 0; $hasMatch = !$isExpense && (int) ($r['match_id'] ?? 0) > 0; ?>
                    <tr>
                        <td>
                            <?php if ($isExpense): ?>
                                <input type="checkbox" class="rowsel" name="row_select[]" value="<?= $i ?>" checked>
                            <?php elseif ($hasMatch): ?>
                                <input type="checkbox" name="row_pay[]" value="<?= $i ?>" checked>
                                <input type="hidden" name="row_match[<?= $i ?>]" value="<?= (int) $r['match_id'] ?>">
                            <?php endif; ?>
                            <input type="hidden" name="row_date[<?= $i ?>]" value="<?= e($r['date']) ?>">
                            <input type="hidden" name="row_amount[<?= $i ?>]" value="<?= (int) $r['amount'] ?>">
                            <input type="hidden" name="row_purpose[<?= $i ?>]" value="<?= e($r['purpose']) ?>">
                        </td>
                        <td><?= dt($r['date']) ?></td>
                        <td><?= e($r['purpose']) ?></td>
                        <td class="num" style="<?= $isExpense ? 'color:var(--error)' : 'color:var(--success)' ?>"><?= money((int) $r['amount']) ?></td>
                        <td>
                            <?php if ($isExpense): ?>
                                Als Ausgabe buchen
                            <?php elseif ($hasMatch): ?>
                                Zahlung → <strong><?= e($r['match_label']) ?></strong>
                            <?php elseif (!empty($r['transit'])): ?>
                                <span class="muted">Geldtransit (Anbieter-Auszahlung) – ignoriert</span>
                            <?php else: ?>
                                <span class="muted">Eingang – keine passende Rechnung</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Übernehmen</button>
            <a href="/bankimport" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<p class="help">Negative Beträge werden als Ausgaben gebucht. Zahlungseingänge werden – sofern eine offene Rechnung per Nummer im Verwendungszweck oder per Betrag erkannt wird – dieser Rechnung als Zahlung zugeordnet (Rechnung wird dann ggf. auf „bezahlt" gesetzt). Nicht zugeordnete Eingänge werden ignoriert.</p>
