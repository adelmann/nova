<?php
/** @var string $secret */
/** @var string $uri */
/** @var array<int,string> $recovery */
use Nova\Services\Totp;
use Nova\Services\QrCode;
?>
<div class="panel">
    <h2>Zwei-Faktor-Authentifizierung einrichten</h2>
    <ol style="line-height:1.7; padding-left:18px;">
        <li>Öffne deine Authenticator-App (Google Authenticator, Authy, 1Password …).</li>
        <li><strong>Scanne den QR-Code</strong> – oder gib den Schlüssel manuell ein.</li>
    </ol>

    <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:flex-start;">
        <div style="background:#fff; padding:10px; border:1px solid var(--border); border-radius:var(--radius); line-height:0;">
            <?= QrCode::svg($uri, 5) ?>
        </div>
        <div style="flex:1; min-width:240px;">
            <div class="field" style="max-width:420px;">
                <label>Einrichtungsschlüssel (manuell)</label>
                <input type="text" readonly onclick="this.select()" value="<?= e(Totp::formatSecret($secret)) ?>"
                       style="font-family:monospace; letter-spacing:1px;">
                <span class="help">Typ: Zeitbasiert (TOTP), 6 Stellen, 30 Sekunden.</span>
            </div>
            <p class="help" style="max-width:520px; word-break:break-all;">
                Manueller Link: <code><?= e($uri) ?></code>
            </p>
        </div>
    </div>

    <div class="flash flash-warn" style="max-width:520px;">
        <strong>Recovery-Codes</strong> – bitte sicher speichern. Damit kommst du rein, falls du dein Gerät verlierst (jeder Code ist einmalig):
        <div style="font-family:monospace; columns:2; margin-top:8px;">
            <?php foreach ($recovery as $code): ?>
                <div><?= e($code) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="post" action="/konto/2fa/aktivieren" style="margin-top:8px;">
        <?= csrf_field() ?>
        <div class="field" style="max-width:260px;">
            <label for="code">Zur Bestätigung: aktueller 6-stelliger Code</label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus placeholder="123456">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">2FA aktivieren</button>
            <a href="/konto" class="btn btn-secondary">Abbrechen</a>
        </div>
    </form>
</div>
