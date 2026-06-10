<?php
/** @var array<string,mixed> $settings */
$s = $settings;
$pmValue = trim((string) ($s['payment_methods'] ?? '')) !== ''
    ? $s['payment_methods']
    : implode("\n", \Nova\Models\CompanySettingsRepository::DEFAULT_PAYMENT_METHODS);
?>
<?= partial('settings/_nav', ['active' => 'invoicing']) ?>

<form method="post" action="/einstellungen/rechnungen">
    <?= csrf_field() ?>
    <div class="panel">
        <h2>Rechnungen &amp; Steuer</h2>
        <div class="form-grid">
            <div class="field full">
                <label class="checkbox">
                    <input type="checkbox" name="is_kleinunternehmer" value="1" <?= (int) $s['is_kleinunternehmer'] === 1 ? 'checked' : '' ?>>
                    Kleinunternehmerregelung nach § 19 UStG aktiv (keine Umsatzsteuer)
                </label>
            </div>
            <div class="field">
                <label for="default_vat_rate">Standard-USt-Satz (%) – falls nicht KU</label>
                <input type="number" id="default_vat_rate" name="default_vat_rate" value="<?= e($s['default_vat_rate']) ?>" min="0" max="99">
            </div>
            <div class="field">
                <label for="default_payment_days">Standard-Zahlungsziel (Tage)</label>
                <input type="number" id="default_payment_days" name="default_payment_days" value="<?= e($s['default_payment_days']) ?>" min="0" max="365">
            </div>
            <div class="field">
                <label for="auto_reminder_days">Autom. Zahlungserinnerung nach X Tagen Überfälligkeit</label>
                <input type="number" id="auto_reminder_days" name="auto_reminder_days" value="<?= e($s['auto_reminder_days'] ?? 0) ?>" min="0" max="365">
                <span class="help">0 = aus. Sonst sendet der Cron einmalig eine freundliche Stufe-1-Erinnerung; weitere Mahnstufen bleiben manuell.</span>
            </div>
            <div class="field">
                <label for="invoice_number_format">Rechnungsnummern-Format</label>
                <input type="text" id="invoice_number_format" name="invoice_number_format" value="<?= e($s['invoice_number_format']) ?>">
                <span class="help">{YYYY} = Jahr, {####} = fortlaufende Nummer (z.B. RE-2026-0001)</span>
            </div>
            <div class="field">
                <label for="quote_number_format">Angebotsnummern-Format</label>
                <input type="text" id="quote_number_format" name="quote_number_format" value="<?= e($s['quote_number_format']) ?>">
                <span class="help">z.B. AN-2026-0001</span>
            </div>
            <div class="field full">
                <label for="kleinunternehmer_note">Kleinunternehmer-Hinweistext</label>
                <input type="text" id="kleinunternehmer_note" name="kleinunternehmer_note" value="<?= e($s['kleinunternehmer_note']) ?>">
            </div>
            <div class="field full">
                <label for="invoice_footer_text">Standard-Fußtext Rechnungen</label>
                <textarea id="invoice_footer_text" name="invoice_footer_text"><?= e($s['invoice_footer_text']) ?></textarea>
            </div>
            <div class="field full">
                <label for="quote_footer_text">Standard-Fußtext Angebote</label>
                <textarea id="quote_footer_text" name="quote_footer_text"><?= e($s['quote_footer_text']) ?></textarea>
            </div>
            <div class="field full">
                <label for="payment_methods">Zahlarten (Auswahl in Formularen)</label>
                <textarea id="payment_methods" name="payment_methods" rows="6"><?= e($pmValue) ?></textarea>
                <span class="help">Eine Zahlart pro Zeile. Erscheinen als Auswahl bei Ausgaben, Zahlungen und Belegen – „Andere…" erlaubt trotzdem eine freie Eingabe.</span>
            </div>
        </div>
    </div>

    <?php
    $dunningFee = isset($s['dunning_fee_cents']) ? amount((int) $s['dunning_fee_cents']) : '0,00';
    $interestRate = rtrim(rtrim(number_format((int) ($s['interest_rate_bp'] ?? 0) / 100, 2, ',', ''), '0'), ',');
    $skontoPct = rtrim(rtrim(number_format((int) ($s['skonto_percent_bp'] ?? 0) / 100, 2, ',', ''), '0'), ',');
    ?>
    <div class="panel">
        <h2>Mahnwesen &amp; Skonto</h2>
        <div class="form-grid">
            <div class="field">
                <label for="dunning_fee">Standard-Mahngebühr (€, ab Stufe 2)</label>
                <input type="text" id="dunning_fee" name="dunning_fee" value="<?= e($dunningFee) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label for="interest_rate">Verzugszinssatz (% p.&nbsp;a., 0 = aus)</label>
                <input type="text" id="interest_rate" name="interest_rate" value="<?= e($interestRate) ?>" inputmode="decimal">
                <span class="help">Gesetzlicher Verzugszins: 5 % über Basiszinssatz (Verbraucher), 9 % (Geschäftskunden).</span>
            </div>
            <div class="field">
                <label for="skonto_percent">Skonto (%, 0 = aus)</label>
                <input type="text" id="skonto_percent" name="skonto_percent" value="<?= e($skontoPct) ?>" inputmode="decimal">
            </div>
            <div class="field">
                <label for="skonto_days">Skontofrist (Tage)</label>
                <input type="number" id="skonto_days" name="skonto_days" value="<?= e((string) ($s['skonto_days'] ?? 0)) ?>" min="0" max="90">
            </div>
        </div>
        <span class="help">Skonto-Konditionen werden auf neuen Rechnungen ausgewiesen; beim Zahlungseingang lässt sich der Restbetrag als Skonto ausgleichen.</span>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Speichern</button>
    </div>
</form>
