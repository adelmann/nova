<?php
/** @var array<int,array{date:string,amount:int,purpose:string}> $rows */
?>
<div class="panel">
    <h2>Vorschau – zu importierende Ausgaben auswählen</h2>
    <form method="post" action="/bankimport/buchen">
        <?= csrf_field() ?>
        <div class="table-wrap" style="box-shadow:none;border:1px solid var(--border);">
            <table>
                <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.rowsel').forEach(c=>c.checked=this.checked)" checked></th><th>Datum</th><th>Verwendungszweck</th><th class="num">Betrag</th><th>Typ</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): $isExpense = $r['amount'] < 0; ?>
                    <tr>
                        <td>
                            <?php if ($isExpense): ?>
                                <input type="checkbox" class="rowsel" name="row_select[]" value="<?= $i ?>" checked>
                            <?php endif; ?>
                            <input type="hidden" name="row_date[<?= $i ?>]" value="<?= e($r['date']) ?>">
                            <input type="hidden" name="row_amount[<?= $i ?>]" value="<?= (int) $r['amount'] ?>">
                            <input type="hidden" name="row_purpose[<?= $i ?>]" value="<?= e($r['purpose']) ?>">
                        </td>
                        <td><?= dt($r['date']) ?></td>
                        <td><?= e($r['purpose']) ?></td>
                        <td class="num" style="<?= $isExpense ? 'color:var(--error)' : 'color:var(--success)' ?>"><?= money((int) $r['amount']) ?></td>
                        <td><?= $isExpense ? 'Ausgabe' : '<span class="muted">Eingang</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Ausgewählte als Ausgaben buchen</button>
            <a href="/bankimport" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<p class="help">Nur negative Beträge (Ausgaben) werden übernommen. Zahlungseingänge bitte direkt bei der jeweiligen Rechnung erfassen.</p>
