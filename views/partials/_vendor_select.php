<?php
/**
 * Lieferanten-Auswahl: Select bestehender Lieferanten + „Andere / neu…"-Freitext.
 * Ein neu eingegebener Name wird beim Speichern automatisch als Lieferant angelegt.
 *
 * @var string $current  aktuell gewählter Name
 * @var string $name     Feldname (Standard: supplier)
 */
$names   = (new \Nova\Models\VendorRepository())->names();
$current = (string) ($current ?? '');
$name    = $name ?? 'supplier';
$id      = 'vsel_' . substr(md5($name), 0, 6);
$isCustom = $current !== '' && !in_array($current, $names, true);
?>
<select name="<?= e($name) ?>" id="<?= $id ?>" onchange="novaMethodToggle(this, '<?= $id ?>_c')">
    <option value="">– bitte wählen –</option>
    <?php foreach ($names as $n): ?>
        <option value="<?= e($n) ?>" <?= $current === $n ? 'selected' : '' ?>><?= e($n) ?></option>
    <?php endforeach; ?>
    <option value="__custom__" <?= $isCustom ? 'selected' : '' ?>>Andere / neu…</option>
</select>
<input type="text" id="<?= $id ?>_c" name="<?= e($name) ?>_custom"
       class="<?= $isCustom ? '' : 'hidden' ?>" style="margin-top:6px;"
       placeholder="Neuen Lieferanten eingeben" value="<?= $isCustom ? e($current) : '' ?>">
