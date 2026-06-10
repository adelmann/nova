<?php
/** @var array<string,mixed> $expense */
/** @var array<int,string> $categories */
/** @var array<int,array<string,mixed>> $receipts */
/** @var string $action */
$ex = $expense;
$amount = isset($ex['amount_cents']) ? amount((int) $ex['amount_cents']) : '0,00';
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="expense_date">Datum</label>
                <input type="date" id="expense_date" name="expense_date" value="<?= e($ex['expense_date']) ?>">
            </div>
            <div class="field">
                <label>Lieferant / Dienstleister</label>
                <?= partial('partials/_vendor_select', ['current' => (string) $ex['supplier']]) ?>
            </div>
            <div class="field">
                <label for="tax_category">EÜR-Kategorie</label>
                <select id="tax_category" name="tax_category">
                    <option value="">– wählen –</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($ex['tax_category'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="category">Freie Kategorie (optional)</label>
                <input type="text" id="category" name="category" value="<?= e($ex['category']) ?>">
            </div>
            <div class="field">
                <label for="amount">Betrag (€, brutto)</label>
                <input type="text" id="amount" name="amount" value="<?= e($amount) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label>Zahlungsart</label>
                <?= partial('partials/_method_select', ['current' => (string) $ex['method']]) ?>
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="paid" <?= $ex['status'] === 'paid' ? 'selected' : '' ?>>Bezahlt</option>
                    <option value="open" <?= $ex['status'] === 'open' ? 'selected' : '' ?>>Offen</option>
                </select>
            </div>
            <div class="field full">
                <label>Belege – als PDF erhaltene Rechnung oder Foto (mehrere möglich)</label>
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <label class="btn btn-secondary camera-label">
                        📎 Datei wählen (PDF/Bild)
                        <input type="file" name="receipt[]" accept="image/*,application/pdf" multiple hidden onchange="novaShowFiles(this,'exp-files')">
                    </label>
                    <label class="btn btn-secondary camera-label">
                        📷 Foto aufnehmen
                        <input type="file" name="receipt[]" accept="image/*" capture="environment" hidden onchange="novaShowFiles(this,'exp-files')">
                    </label>
                    <span class="help hidden" id="exp-files"></span>
                </div>
                <input type="hidden" name="receipt_type" value="eingangsrechnung">
            </div>
            <div class="field full">
                <label for="note">Notiz</label>
                <textarea id="note" name="note"><?= e($ex['note']) ?></textarea>
            </div>
        </div>

        <?php if ($receipts !== []): ?>
            <p class="help">Zugeordnete Belege:
                <?php foreach ($receipts as $r): ?>
                    <a href="/belege/<?= (int) $r['id'] ?>/download" target="_blank"><?= e($r['original_name']) ?></a>&nbsp;
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/ausgaben" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<p class="help">Status „Bezahlt" erzeugt automatisch einen Eintrag im Buchungsjournal (Ausgabe). Betragsänderungen werden per Differenz-Gegenbuchung nachgeführt – das Journal bleibt unveränderbar (GoBD).</p>
