<?php
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort vergessen · <?= e($appName) ?></title>
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
<div class="login-wrap">
    <div class="login-box">
        <h1 class="brand-title"><span class="brand-mark" aria-hidden="true"></span><?= e($appName) ?></h1>
        <p class="sub">Passwort zurücksetzen</p>

        <?php foreach (flash_messages() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/passwort-vergessen">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="username">
            </div>
            <button type="submit" class="btn">Link zum Zurücksetzen senden</button>
        </form>
        <p class="help" style="margin-top:14px;"><a href="/login">← Zurück zur Anmeldung</a></p>
    </div>
</div>
<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/service-worker.js").catch(function(){})})}</script>
</body>
</html>
