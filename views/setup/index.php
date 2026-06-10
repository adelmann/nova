<?php
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Einrichtung · <?= e($appName) ?></title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2f6fed">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Nova">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-wrap" style="justify-content:flex-start; padding-top:5vh;">
    <div class="login-box" style="width:520px;">
        <h1 class="brand-title"><span class="brand-mark" aria-hidden="true"></span><?= e($appName) ?> einrichten</h1>
        <p class="sub">Willkommen! Lege deinen Zugang und die Firmen-Basisdaten an. Das geht später unter „Einstellungen" jederzeit.</p>

        <?php foreach (flash_messages() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/setup">
            <?= csrf_field() ?>

            <h2 style="font-size:14px; margin:6px 0 10px;">Dein Zugang</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" placeholder="z.B. Max Mustermann" required>
                </div>
                <div class="field">
                    <label for="email">E-Mail (Login)</label>
                    <input type="email" id="email" name="email" required autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">Passwort (min. 8 Zeichen)</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="password_confirm">Passwort wiederholen</label>
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                </div>
            </div>
            <p class="help" style="margin-top:4px;">🔒 Im Anschluss empfehlen wir dringend, die <strong>Zwei-Faktor-Authentifizierung</strong> zu aktivieren – das geht direkt nach der Einrichtung in deinem Konto.</p>

            <h2 style="font-size:14px; margin:16px 0 10px;">Firmen-Basisdaten</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="company_name">Firmenname</label>
                    <input type="text" id="company_name" name="company_name" required>
                </div>
                <div class="field">
                    <label for="owner_name">Inhaber / Name</label>
                    <input type="text" id="owner_name" name="owner_name">
                </div>
                <div class="field full">
                    <label for="address_line1">Adresse</label>
                    <input type="text" id="address_line1" name="address_line1" placeholder="Straße und Hausnummer">
                </div>
                <div class="field">
                    <label for="zip">PLZ</label>
                    <input type="text" id="zip" name="zip">
                </div>
                <div class="field">
                    <label for="city">Ort</label>
                    <input type="text" id="city" name="city">
                </div>
                <div class="field full">
                    <label for="company_email">Firmen-E-Mail</label>
                    <input type="email" id="company_email" name="company_email">
                </div>
                <div class="field full">
                    <label class="checkbox">
                        <input type="checkbox" name="is_kleinunternehmer" value="1" checked>
                        Kleinunternehmer nach § 19 UStG (keine Umsatzsteuer ausweisen)
                    </label>
                </div>
            </div>

            <div style="margin-top:16px;">
                <?= partial('partials/_disclaimer') ?>
            </div>
            <label class="checkbox" style="margin-top:12px;">
                <input type="checkbox" name="accept_terms" value="1" required>
                Ich habe den Haftungsausschluss gelesen und nutze Nova auf eigenes Risiko.
            </label>

            <button type="submit" class="btn" style="width:100%; justify-content:center; margin-top:16px;">Einrichtung abschließen</button>
        </form>
    </div>
</div>
<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/service-worker.js").catch(function(){})})}</script>
</body>
</html>
