<?php
/** @var array<string,mixed> $profile */
/** @var array<int,string> $categories */
/** @var string $action */
$p = $profile;
$amount = isset($p['amount_cents']) ? amount((int) $p['amount_cents']) : '0,00';
$units = ['month' => 'monatlich', 'quarter' => 'quartalsweise', 'year' => 'jährlich'];
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full">
                <label for="title">Bezeichnung</label>
                <input type="text" id="title" name="title" value="<?= e($p['title']) ?>" placeholder="z.B. Büromiete, Software-Abo">
            </div>
            <div class="field">
                <label>Lieferant / Dienstleister</label>
                <?= partial('partials/_vendor_select', ['current' => (string) $p['supplier']]) ?>
            </div>
            <div class="field">
                <label for="tax_category">EÜR-Kategorie</label>
                <select id="tax_category" name="tax_category">
                    <option value="">– wählen –</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($p['tax_category'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="category">Freie Kategorie (optional)</label>
                <input type="text" id="category" name="category" value="<?= e($p['category']) ?>">
            </div>
            <div class="field">
                <label for="amount">Betrag (€, brutto)</label>
                <input type="text" id="amount" name="amount" value="<?= e($amount) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label>Zahlungsart</label>
                <?= partial('partials/_method_select', ['current' => (string) $p['method']]) ?>
            </div>
            <div class="field">
                <label for="interval_unit">Intervall</label>
                <select id="interval_unit" name="interval_unit">
                    <?php foreach ($units as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= ($p['interval_unit'] ?? 'month') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="next_date">Nächste Fälligkeit</label>
                <input type="date" id="next_date" name="next_date" value="<?= e($p['next_date']) ?>">
            </div>
            <div class="field">
                <label for="active">Status</label>
                <select id="active" name="active">
                    <option value="1" <?= (int) ($p['active'] ?? 1) === 1 ? 'selected' : '' ?>>aktiv</option>
                    <option value="0" <?= (int) ($p['active'] ?? 1) === 0 ? 'selected' : '' ?>>pausiert</option>
                </select>
            </div>
            <div class="field full">
                <label for="note">Notiz</label>
                <textarea id="note" name="note"><?= e($p['note']) ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/dauerausgaben" class="btn btn-secondary">Abbrechen</a>
            <?php if (!empty($p['id'])): ?>
                <span style="flex:1"></span>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php if (!empty($p['id'])): ?>
    <form method="post" action="/dauerausgaben/<?= (int) $p['id'] ?>/loeschen" onsubmit="return confirm('Dieses Profil wirklich löschen? Bereits gebuchte Ausgaben bleiben erhalten.');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger">Profil löschen</button>
    </form>
<?php endif; ?>
<p class="help">Pausierte Profile erzeugen keine Ausgaben. Beim nächsten Cron-Lauf nach dem Fälligkeitstag wird automatisch eine bezahlte Ausgabe gebucht und das Datum um ein Intervall weitergesetzt.</p>
