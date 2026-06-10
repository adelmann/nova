<?php
/** @var array<string,mixed> $invoice */
$inv = $invoice;
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Danke für deine Zahlung</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box" style="width:420px; text-align:center;">
        <h1 style="font-size:42px; margin:0;">✓</h1>
        <h2 style="margin:6px 0;">Vielen Dank!</h2>
        <p class="muted">Deine Zahlung zu Rechnung <?= e($inv['number']) ?> wurde angestoßen.
            Die Bestätigung erfolgt automatisch, sobald der Zahlungsanbieter den Eingang meldet.</p>
    </div>
</div>
</body>
</html>
