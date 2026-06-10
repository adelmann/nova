<?php
/** @var array<string,mixed> $user */
$twoFa = (int) ($user['totp_enabled'] ?? 0) === 1;
?>

<div class="panel">
    <h2>Zwei-Faktor-Authentifizierung</h2>
    <?php if ($twoFa): ?>
        <p><span class="badge badge-paid">aktiv</span> Beim Login wird zusätzlich ein Code aus deiner Authenticator-App abgefragt.</p>
        <form method="post" action="/konto/2fa/deaktivieren" data-confirm="Zwei-Faktor-Authentifizierung wirklich deaktivieren?">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field">
                    <label for="twofa_pw">Aktuelles Passwort (zur Bestätigung)</label>
                    <input type="password" id="twofa_pw" name="current_password" required autocomplete="current-password">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-danger">2FA deaktivieren</button>
            </div>
        </form>
    <?php else: ?>
        <div class="promo">
            <div class="promo-icon">🔒</div>
            <div class="promo-body">
                <strong>Empfohlen: Schütze dein Konto mit 2FA.</strong>
                <p>Schon wer dein Passwort kennt, käme sonst an deine Buchhaltungs- und Kundendaten. Mit Zwei-Faktor-Authentifizierung wird beim Login zusätzlich ein Einmal-Code aus deiner Authenticator-App (Google Authenticator, Authy, 1Password …) verlangt – das macht ein fremder Zugriff praktisch unmöglich. Dauert keine Minute.</p>
                <form method="post" action="/konto/2fa/start">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn">Jetzt 2FA aktivieren</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>E-Mail-Adresse ändern</h2>
    <form method="post" action="/konto/email">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="email">E-Mail-Adresse</label>
                <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required autocomplete="username">
            </div>
            <div class="field">
                <label for="email_current_password">Aktuelles Passwort (zur Bestätigung)</label>
                <input type="password" id="email_current_password" name="current_password" required autocomplete="current-password">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">E-Mail speichern</button>
        </div>
    </form>
</div>

<div class="panel">
    <h2>Passwort ändern</h2>
    <form method="post" action="/konto/passwort">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full">
                <label for="pw_current">Aktuelles Passwort</label>
                <input type="password" id="pw_current" name="current_password" required autocomplete="current-password">
            </div>
            <div class="field">
                <label for="new_password">Neues Passwort</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
                <span class="help">Mindestens 8 Zeichen.</span>
            </div>
            <div class="field">
                <label for="new_password_confirm">Neues Passwort wiederholen</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Passwort ändern</button>
        </div>
    </form>
</div>
