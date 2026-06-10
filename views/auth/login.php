<?php
/** @var string $imprint_url */
/** @var string $privacy_url */
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
$imprintUrl = $imprint_url ?? '';
$privacyUrl = $privacy_url ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anmelden · <?= e($appName) ?></title>
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
        <p class="sub">Business- &amp; Buchhaltungstool</p>

        <?php foreach (flash_messages() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="/login">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn">Anmelden</button>
        </form>
        <p class="help" style="margin-top:14px;"><a href="/passwort-vergessen">Passwort vergessen?</a></p>
    </div>
    <p class="powered">Powered by <a href="https://adelmann-solutions.com" target="_blank" rel="noopener">adelmann-solutions.com</a></p>
</div>

<?php if ($imprintUrl !== '' || $privacyUrl !== ''): ?>
    <footer class="legal-footer">
        <?php if ($imprintUrl !== ''): ?>
            <a href="<?= e($imprintUrl) ?>" target="_blank" rel="noopener">Impressum</a>
        <?php endif; ?>
        <?php if ($imprintUrl !== '' && $privacyUrl !== ''): ?>
            <span class="sep">·</span>
        <?php endif; ?>
        <?php if ($privacyUrl !== ''): ?>
            <a href="<?= e($privacyUrl) ?>" target="_blank" rel="noopener">Datenschutz</a>
        <?php endif; ?>
    </footer>
<?php endif; ?>
<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/service-worker.js").catch(function(){})})}</script>
</body>
</html>
