<?php
/** @var array<string,mixed> $settings */
$s = $settings;
?>
<?= partial('settings/_nav', ['active' => 'company']) ?>

<form method="post" action="/einstellungen" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="panel">
        <h2>Unternehmensdaten</h2>
        <div class="form-grid">
            <div class="field">
                <label for="company_name">Firmenname</label>
                <input type="text" id="company_name" name="company_name" value="<?= e($s['company_name']) ?>">
            </div>
            <div class="field">
                <label for="owner_name">Inhaber / Name</label>
                <input type="text" id="owner_name" name="owner_name" value="<?= e($s['owner_name']) ?>">
            </div>
            <div class="field full">
                <label for="address_line1">Adresse</label>
                <input type="text" id="address_line1" name="address_line1" value="<?= e($s['address_line1']) ?>" placeholder="Straße und Hausnummer">
            </div>
            <div class="field">
                <label for="zip">PLZ</label>
                <input type="text" id="zip" name="zip" value="<?= e($s['zip']) ?>">
            </div>
            <div class="field">
                <label for="city">Ort</label>
                <input type="text" id="city" name="city" value="<?= e($s['city']) ?>">
            </div>
            <div class="field">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" value="<?= e($s['email']) ?>">
            </div>
            <div class="field">
                <label for="phone">Telefon</label>
                <input type="tel" id="phone" name="phone" value="<?= e($s['phone']) ?>">
            </div>
            <div class="field">
                <label for="website">Website</label>
                <input type="text" id="website" name="website" value="<?= e($s['website'] ?? '') ?>" placeholder="z.B. www.meine-domain.de">
            </div>
            <div class="field full">
                <label for="social_media">Social Media (optional)</label>
                <input type="text" id="social_media" name="social_media" value="<?= e($s['social_media'] ?? '') ?>" placeholder="z.B. Instagram: @meinprofil · LinkedIn: /in/name">
                <span class="help">Frei pflegbar – erscheint dezent in der Fußzeile von Rechnungen und Angeboten.</span>
            </div>
            <div class="field">
                <label for="tax_number">Steuernummer (optional)</label>
                <input type="text" id="tax_number" name="tax_number" value="<?= e($s['tax_number']) ?>">
            </div>
            <div class="field">
                <label for="vat_id">USt-ID (optional)</label>
                <input type="text" id="vat_id" name="vat_id" value="<?= e($s['vat_id']) ?>">
            </div>
        </div>
    </div>

    <div class="panel">
        <h2>Bankverbindung</h2>
        <div class="form-grid">
            <div class="field">
                <label for="bank_name">Bank</label>
                <input type="text" id="bank_name" name="bank_name" value="<?= e($s['bank_name']) ?>">
            </div>
            <div class="field">
                <label for="iban">IBAN</label>
                <input type="text" id="iban" name="iban" value="<?= e($s['iban']) ?>">
            </div>
            <div class="field">
                <label for="bic">BIC</label>
                <input type="text" id="bic" name="bic" value="<?= e($s['bic']) ?>">
            </div>
        </div>
    </div>

    <div class="panel">
        <h2>Logo</h2>
        <div class="form-grid">
            <div class="field full">
                <label for="logo">Logo (JPG/PNG)</label>
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg">
                <?php if (!empty($s['logo_path'])): ?>
                    <span class="help">Aktuelles Logo: <?= e($s['logo_path']) ?></span>
                <?php else: ?>
                    <span class="help">Erscheint im Kopfbereich von Rechnungen und Angeboten.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="panel">
        <h2>Rechtliches</h2>
        <p class="help" style="margin-top:0">Diese Links erscheinen unten links auf der Anmeldeseite. Leer lassen, um den jeweiligen Link auszublenden.</p>
        <div class="form-grid">
            <div class="field">
                <label for="imprint_url">Impressum – Link</label>
                <input type="text" id="imprint_url" name="imprint_url" value="<?= e($s['imprint_url'] ?? '') ?>" placeholder="z.B. https://meine-domain.de/impressum">
            </div>
            <div class="field">
                <label for="privacy_url">Datenschutz – Link</label>
                <input type="text" id="privacy_url" name="privacy_url" value="<?= e($s['privacy_url'] ?? '') ?>" placeholder="z.B. https://meine-domain.de/datenschutz">
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Speichern</button>
    </div>
</form>
