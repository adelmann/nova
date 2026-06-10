<?php
/** @var array<string,mixed> $item */
/** @var string $action */
$it = $item;
?>
<div class="toolbar">
    <a href="/katalog" class="btn btn-secondary btn-sm">← Zurück</a>
</div>

<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full">
                <label for="name">Bezeichnung</label>
                <input type="text" id="name" name="name" value="<?= e($it['name']) ?>" required placeholder="z.B. Beratung / Entwicklung / Hosting-Paket">
            </div>
            <div class="field">
                <label for="unit">Einheit</label>
                <input type="text" id="unit" name="unit" value="<?= e($it['unit']) ?>" placeholder="Std, Stk, Pauschal…">
            </div>
            <div class="field">
                <label for="unit_price">Einzelpreis (€, netto)</label>
                <input type="text" id="unit_price" name="unit_price" value="<?= e(amount((int) $it['unit_price_cents'])) ?>" inputmode="decimal">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/katalog" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
