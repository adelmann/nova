<?php use Nova\Models\ExpenseRepository; ?>
<div class="panel">
    <form method="post" action="/belege" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full">
                <label>Beleg – als PDF erhaltene Rechnung oder Foto (mehrere möglich, max. 10 MB je Datei)</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <label class="btn btn-secondary camera-label">
                        📎 Datei wählen (PDF/Bild)
                        <input type="file" name="receipt[]" accept="image/*,application/pdf" multiple hidden onchange="novaShowFiles(this,'rcpt-files')">
                    </label>
                    <label class="btn btn-secondary camera-label">
                        📷 Foto aufnehmen
                        <input type="file" name="receipt[]" accept="image/*" capture="environment" hidden onchange="novaShowFiles(this,'rcpt-files')">
                    </label>
                    <span class="help hidden" id="rcpt-files"></span>
                </div>
            </div>
            <div class="field">
                <label for="type">Belegtyp</label>
                <select id="type" name="type">
                    <option value="quittung">Quittung</option>
                    <option value="eingangsrechnung">Eingangsrechnung</option>
                    <option value="kontoauszug">Kontoauszug</option>
                    <option value="sonstiges">Sonstiges</option>
                </select>
            </div>
        </div>

        <div class="panel" style="background:var(--subtle); margin:6px 0 0;">
            <label class="checkbox" style="font-weight:600;">
                <input type="checkbox" id="as_expense" name="as_expense" value="1" onchange="document.getElementById('expense-fields').style.display=this.checked?'grid':'none'">
                Direkt als Ausgabe verbuchen (mit Betrag)
            </label>
            <p class="help" style="margin-top:6px;">Der Beleg wird dann einer neuen Ausgabe zugeordnet und fließt in die EÜR ein. Ohne Häkchen wird nur das Dokument archiviert.</p>
            <div class="form-grid" id="expense-fields" style="display:none; margin-top:10px;">
                <div class="field">
                    <label for="amount">Betrag (€, brutto)</label>
                    <input type="text" id="amount" name="amount" inputmode="decimal" placeholder="z.B. 49,90">
                </div>
                <div class="field">
                    <label for="expense_date">Datum</label>
                    <input type="date" id="expense_date" name="expense_date" value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="field">
                    <label>Lieferant / Dienstleister</label>
                    <?= partial('partials/_vendor_select', ['current' => '']) ?>
                </div>
                <div class="field">
                    <label for="tax_category">EÜR-Kategorie</label>
                    <select id="tax_category" name="tax_category">
                        <?php foreach (ExpenseRepository::taxCategories() as $cat): ?>
                            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Zahlart</label>
                    <?= partial('partials/_method_select', ['current' => '']) ?>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="paid">Bezahlt</option>
                        <option value="open">Offen</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Hochladen</button>
            <a href="/belege" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<p class="help">Tipp: Belege lassen sich auch direkt beim Erfassen einer Ausgabe hochladen (Menü → Ausgaben → Neu).</p>
