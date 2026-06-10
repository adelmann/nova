<?php
/** @var array<string,mixed> $asset */
/** @var array<string,int> $lifes */
/** @var string $action */
$a = $asset;
$cost = isset($a['cost_cents']) ? amount((int) $a['cost_cents']) : '0,00';
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full">
                <label for="name">Bezeichnung</label>
                <input type="text" id="name" name="name" value="<?= e($a['name']) ?>" placeholder="z.B. Notebook Dell XPS 13">
            </div>
            <div class="field">
                <label>Lieferant / Händler</label>
                <?= partial('partials/_vendor_select', ['current' => (string) $a['supplier']]) ?>
            </div>
            <div class="field">
                <label for="acquired_date">Anschaffungsdatum</label>
                <input type="date" id="acquired_date" name="acquired_date" value="<?= e($a['acquired_date']) ?>">
            </div>
            <div class="field">
                <label for="cost">Anschaffungskosten (€)</label>
                <input type="text" id="cost" name="cost" value="<?= e($cost) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label for="method">Abschreibungsart</label>
                <select id="method" name="method">
                    <option value="linear" <?= ($a['method'] ?? 'linear') === 'linear' ? 'selected' : '' ?>>Linear (über Nutzungsdauer)</option>
                    <option value="gwg" <?= ($a['method'] ?? '') === 'gwg' ? 'selected' : '' ?>>GWG – sofort voll abschreiben (bis 800&nbsp;€ netto)</option>
                </select>
            </div>
            <div class="field">
                <label for="useful_life_years">Nutzungsdauer (Jahre)</label>
                <input type="number" id="useful_life_years" name="useful_life_years" min="1" max="50" value="<?= e((string) ($a['useful_life_years'] ?? 3)) ?>" list="afa-lifes">
                <datalist id="afa-lifes">
                    <?php foreach ($lifes as $label => $years): ?>
                        <option value="<?= (int) $years ?>"><?= e($label) ?> (<?= (int) $years ?> Jahre)</option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="field full">
                <label for="note">Notiz</label>
                <textarea id="note" name="note"><?= e($a['note']) ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/anlagen" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
<p class="help">Bei <strong>GWG</strong> wird die Nutzungsdauer ignoriert – das Gut wird im Anschaffungsjahr voll abgeschrieben. Bei <strong>linearer</strong> AfA verteilt Nova die Kosten gleichmäßig über die Nutzungsdauer und berücksichtigt im ersten Jahr nur die Monate ab dem Kauf (pro rata temporis).</p>
