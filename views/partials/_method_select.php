<?php
/**
 * Zahlart-Auswahl: Select mit gepflegten Vorschlägen + „Andere…"-Freitext.
 *
 * @var array<int,string> $methods  Vorschläge aus den Einstellungen
 * @var string            $current   aktuell gewählter Wert (ggf. frei)
 * @var string            $name      Feldname (Standard: method)
 */
if (!isset($methods) || !is_array($methods)) {
    $methods = (new \Nova\Models\CompanySettingsRepository())->paymentMethods();
}
$current = (string) ($current ?? '');
$name    = $name ?? 'method';
$id      = 'msel_' . substr(md5($name . implode('', $methods)), 0, 6);
$isCustom = $current !== '' && !in_array($current, $methods, true);
?>
<select name="<?= e($name) ?>" id="<?= $id ?>" onchange="novaMethodToggle(this, '<?= $id ?>_c')">
    <?php foreach ($methods as $m): ?>
        <option value="<?= e($m) ?>" <?= $current === $m ? 'selected' : '' ?>><?= e($m) ?></option>
    <?php endforeach; ?>
    <option value="__custom__" <?= $isCustom ? 'selected' : '' ?>>Andere…</option>
</select>
<input type="text" id="<?= $id ?>_c" name="<?= e($name) ?>_custom"
       class="<?= $isCustom ? '' : 'hidden' ?>" style="margin-top:6px;"
       placeholder="Zahlart eingeben" value="<?= $isCustom ? e($current) : '' ?>">
