<?php
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bestätigung · <?= e($appName) ?></title>
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
        <p class="sub">Zwei-Faktor-Bestätigung</p>

        <?php foreach (flash_messages() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/login/2fa">
            <?= csrf_field() ?>
            <div class="field">
                <label for="code">6-stelliger Code aus deiner Authenticator-App</label>
                <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
                       pattern="[0-9A-Za-z\- ]*" required autofocus placeholder="123456">
                <span class="help">Alternativ einen Recovery-Code eingeben.</span>
            </div>
            <button type="submit" class="btn">Bestätigen</button>
        </form>
        <p class="help" style="margin-top:14px;"><a href="/login">← Abbrechen</a></p>
    </div>
</div>
<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/service-worker.js").catch(function(){})})}</script>
</body>
</html>
