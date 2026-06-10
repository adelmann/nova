<?php
/** @var array<string,mixed> $vendor */
/** @var string $action */
$v = $vendor;
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="name">Name (Firma / Dienstleister)</label>
                <input type="text" id="name" name="name" value="<?= e($v['name']) ?>" required>
            </div>
            <div class="field">
                <label for="contact_name">Ansprechpartner</label>
                <input type="text" id="contact_name" name="contact_name" value="<?= e($v['contact_name']) ?>">
            </div>
            <div class="field">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" value="<?= e($v['email']) ?>">
            </div>
            <div class="field">
                <label for="phone">Telefon</label>
                <input type="tel" id="phone" name="phone" value="<?= e($v['phone']) ?>">
            </div>
            <div class="field">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" value="<?= e($v['website']) ?>">
            </div>
            <div class="field">
                <label for="vat_id">USt-ID (optional)</label>
                <input type="text" id="vat_id" name="vat_id" value="<?= e($v['vat_id']) ?>">
            </div>
            <div class="field full">
                <label for="address_line1">Adresse</label>
                <input type="text" id="address_line1" name="address_line1" value="<?= e($v['address_line1']) ?>" placeholder="Straße und Hausnummer">
            </div>
            <div class="field">
                <label for="zip">PLZ</label>
                <input type="text" id="zip" name="zip" value="<?= e($v['zip']) ?>">
            </div>
            <div class="field">
                <label for="city">Ort</label>
                <input type="text" id="city" name="city" value="<?= e($v['city']) ?>">
            </div>
            <div class="field full">
                <label for="note">Notiz</label>
                <textarea id="note" name="note"><?= e($v['note']) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/lieferanten" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
