<?php
/** @var array<string,mixed> $settings */
$s = $settings;
use Nova\Models\CompanySettingsRepository as CS;
$tpl = static fn (string $key, string $default): string =>
    trim((string) ($s[$key] ?? '')) !== '' ? (string) $s[$key] : $default;
?>
<?= partial('settings/_nav', ['active' => 'email']) ?>

<form method="post" action="/einstellungen/email">
    <?= csrf_field() ?>

    <div class="panel">
        <h2>E-Mail-Versand (SMTP)</h2>
        <p class="help" style="margin-top:0">Für den Versand von Rechnungen, Angeboten und Mahnungen. Ohne SMTP-Host wird die PHP-Funktion <code>mail()</code> des Servers genutzt.</p>
        <div class="form-grid">
            <div class="field">
                <label for="mail_from_email">Absender-E-Mail</label>
                <input type="email" id="mail_from_email" name="mail_from_email" value="<?= e($s['mail_from_email'] ?? '') ?>" placeholder="z.B. rechnung@meine-domain.de">
            </div>
            <div class="field">
                <label for="mail_from_name">Absender-Name</label>
                <input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($s['mail_from_name'] ?? '') ?>" placeholder="z.B. Mein Unternehmen">
            </div>
            <div class="field">
                <label for="smtp_host">SMTP-Host (optional)</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?= e($s['smtp_host'] ?? '') ?>" placeholder="z.B. smtp.strato.de">
            </div>
            <div class="field">
                <label for="smtp_port">SMTP-Port</label>
                <input type="number" id="smtp_port" name="smtp_port" value="<?= e($s['smtp_port'] ?? 587) ?>" min="1" max="65535">
            </div>
            <div class="field">
                <label for="smtp_encryption">Verschlüsselung</label>
                <select id="smtp_encryption" name="smtp_encryption">
                    <?php foreach (['tls' => 'STARTTLS (Port 587)', 'ssl' => 'SSL/TLS (Port 465)', 'none' => 'Keine'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($s['smtp_encryption'] ?? 'tls') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="smtp_user">SMTP-Benutzer</label>
                <input type="text" id="smtp_user" name="smtp_user" value="<?= e($s['smtp_user'] ?? '') ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="smtp_pass">SMTP-Passwort</label>
                <input type="password" id="smtp_pass" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?= !empty($s['smtp_pass']) ? '•••••••• (gespeichert – zum Ändern neu eingeben)' : '' ?>">
            </div>
        </div>
    </div>

    <div class="panel">
        <h2>Signatur &amp; Textvorlagen</h2>
        <p class="help" style="margin-top:0">
            Platzhalter werden beim Versand ersetzt:
            <code>{kunde}</code> <code>{nummer}</code> <code>{datum}</code> <code>{betrag}</code> <code>{faellig}</code> <code>{firma}</code>.
            Leere Felder verwenden den eingebauten Standardtext.
        </p>
        <div class="form-grid">
            <div class="field full">
                <label for="email_signature">Signatur (an jede E-Mail angehängt)</label>
                <textarea id="email_signature" name="email_signature" rows="4"><?= e($tpl('email_signature', CS::DEFAULT_EMAIL_SIGNATURE)) ?></textarea>
            </div>

            <div class="field">
                <label for="invoice_email_subject">Rechnung – Betreff</label>
                <input type="text" id="invoice_email_subject" name="invoice_email_subject" value="<?= e($tpl('invoice_email_subject', CS::DEFAULT_INVOICE_EMAIL_SUBJECT)) ?>">
            </div>
            <div class="field">
                <label for="quote_email_subject">Angebot – Betreff</label>
                <input type="text" id="quote_email_subject" name="quote_email_subject" value="<?= e($tpl('quote_email_subject', CS::DEFAULT_QUOTE_EMAIL_SUBJECT)) ?>">
            </div>
            <div class="field full">
                <label for="invoice_email_body">Rechnung – E-Mail-Text</label>
                <textarea id="invoice_email_body" name="invoice_email_body" rows="5"><?= e($tpl('invoice_email_body', CS::DEFAULT_INVOICE_EMAIL_BODY)) ?></textarea>
            </div>
            <div class="field full">
                <label for="quote_email_body">Angebot – E-Mail-Text</label>
                <textarea id="quote_email_body" name="quote_email_body" rows="5"><?= e($tpl('quote_email_body', CS::DEFAULT_QUOTE_EMAIL_BODY)) ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Speichern</button>
    </div>
</form>
