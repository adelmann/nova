<?php
/** @var array<string,mixed> $customer */
/** @var string $action */
$c = $customer;
?>
<div class="panel">
    <form method="post" action="<?= e($action) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="company_name">Firmenname</label>
                <input type="text" id="company_name" name="company_name" value="<?= e($c['company_name']) ?>">
            </div>
            <div class="field">
                <label for="contact_name">Ansprechpartner</label>
                <input type="text" id="contact_name" name="contact_name" value="<?= e($c['contact_name']) ?>">
            </div>
            <div class="field">
                <label for="type">Kundentyp</label>
                <select id="type" name="type">
                    <option value="business" <?= $c['type'] === 'business' ? 'selected' : '' ?>>Geschäftskunde</option>
                    <option value="private" <?= $c['type'] === 'private' ? 'selected' : '' ?>>Privatkunde</option>
                </select>
            </div>
            <div class="field">
                <label for="vat_id">USt-ID (optional)</label>
                <input type="text" id="vat_id" name="vat_id" value="<?= e($c['vat_id']) ?>">
            </div>
            <div class="field full">
                <label for="address_line1">Adresse</label>
                <input type="text" id="address_line1" name="address_line1" value="<?= e($c['address_line1']) ?>" placeholder="Straße und Hausnummer">
            </div>
            <div class="field full">
                <input type="text" id="address_line2" name="address_line2" value="<?= e($c['address_line2']) ?>" placeholder="Adresszusatz (optional)">
            </div>
            <div class="field">
                <label for="zip">PLZ</label>
                <input type="text" id="zip" name="zip" value="<?= e($c['zip']) ?>">
            </div>
            <div class="field">
                <label for="city">Ort</label>
                <input type="text" id="city" name="city" value="<?= e($c['city']) ?>">
            </div>
            <div class="field">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" value="<?= e($c['email']) ?>">
            </div>
            <div class="field">
                <label for="phone">Telefon</label>
                <input type="tel" id="phone" name="phone" value="<?= e($c['phone']) ?>">
            </div>
            <div class="field full">
                <label for="notes">Notizen</label>
                <textarea id="notes" name="notes"><?= e($c['notes']) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Speichern</button>
            <a href="/kunden" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
