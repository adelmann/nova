<?php
/** @var array<string,mixed> $settings */
$s = $settings;
$appUrl = rtrim((string) ($GLOBALS['nova_config']['app_url'] ?? ''), '/');
if ($appUrl === '') {
    // Aus dem aktuellen Request ableiten, damit nie ein Platzhalter erscheint.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    $appUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$base = $appUrl;
?>
<?= partial('settings/_nav', ['active' => 'payments']) ?>

<form method="post" action="/einstellungen/zahlung">
    <?= csrf_field() ?>

    <div class="panel">
        <h2>Online-Zahlung</h2>
        <p class="help" style="margin-top:0">Optional. Ohne hinterlegte Schlüssel erscheint kein Bezahllink. Zahlungseingänge werden automatisch verbucht – der Bruttobetrag als Einnahme, die Anbietergebühr als Ausgabe.</p>
        <div class="form-grid">
            <div class="field">
                <label for="payment_fee_category">EÜR-Kategorie für Gebühren</label>
                <input type="text" id="payment_fee_category" name="payment_fee_category" value="<?= e($s['payment_fee_category'] ?? 'Bankgebühren') ?>">
            </div>
        </div>
    </div>

    <div class="panel">
        <h2>Stripe (Kreditkarte, Apple/Google Pay)</h2>
        <div class="form-grid">
            <div class="field">
                <label for="stripe_secret_key">Stripe Secret Key</label>
                <input type="password" id="stripe_secret_key" name="stripe_secret_key" value="" autocomplete="new-password" placeholder="<?= !empty($s['stripe_secret_key']) ? '•••••••• (gespeichert)' : 'sk_live_… oder sk_test_…' ?>">
            </div>
            <div class="field">
                <label for="stripe_webhook_secret">Stripe Webhook Secret</label>
                <input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" value="" autocomplete="new-password" placeholder="<?= !empty($s['stripe_webhook_secret']) ? '•••••••• (gespeichert)' : 'whsec_…' ?>">
            </div>
        </div>
        <div class="flash flash-warn" style="margin-top:6px;">
            <strong>Webhook im Stripe-Dashboard einrichten</strong> auf folgende URL (Event <code>checkout.session.completed</code>):
            <div class="field" style="margin-top:8px;"><input type="text" readonly onclick="this.select()" value="<?= e($base) ?>/webhook/stripe"></div>
            <span class="help">Das dort angezeigte „Signing secret" (<code>whsec_…</code>) oben als Webhook Secret eintragen.</span>
        </div>
    </div>

    <div class="panel">
        <h2>PayPal</h2>
        <p class="help" style="margin-top:0">Client-ID und Secret aus deiner PayPal-App (Developer-Dashboard) eintragen. Im Modus „Sandbox" wird mit Testkonten gezahlt, „Live" für echte Zahlungen. Ein Webhook ist nicht nötig – die Zahlung wird bei der Rückkehr abgeschlossen und verbucht (inkl. Gebühr).</p>
        <div class="form-grid">
            <div class="field">
                <label for="paypal_client_id">PayPal Client-ID</label>
                <input type="text" id="paypal_client_id" name="paypal_client_id" value="<?= e($s['paypal_client_id'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="paypal_secret">PayPal Secret</label>
                <input type="password" id="paypal_secret" name="paypal_secret" value="" autocomplete="new-password" placeholder="<?= !empty($s['paypal_secret']) ? '•••••••• (gespeichert)' : '' ?>">
            </div>
            <div class="field">
                <label for="paypal_mode">Modus</label>
                <select id="paypal_mode" name="paypal_mode">
                    <?php foreach (['sandbox' => 'Sandbox (Test)', 'live' => 'Live'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($s['paypal_mode'] ?? 'sandbox') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Speichern</button>
    </div>
</form>
<p class="help"><strong>Datenschutz:</strong> Zahlungsdaten werden ausschließlich beim Anbieter eingegeben. Bitte AV-Vertrag mit dem Anbieter akzeptieren und die Datenschutzerklärung entsprechend ergänzen.</p>
