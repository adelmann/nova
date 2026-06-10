<?php
/** @var array<string,mixed> $invoice */
/** @var array<string,mixed> $settings */
/** @var array<int,string> $providers */
/** @var int $open */
use Nova\Services\PaymentService;
$inv = $invoice;
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rechnung <?= e($inv['number']) ?> bezahlen</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-box" style="width:420px;">
        <h1 class="brand-title"><span class="brand-mark" aria-hidden="true"></span><?= e($settings['company_name'] ?: $appName) ?></h1>
        <p class="sub">Rechnung <?= e($inv['number']) ?></p>

        <?php foreach (flash_messages() as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <?php if ($open <= 0): ?>
            <div class="flash flash-success">Diese Rechnung ist bereits bezahlt. Vielen Dank!</div>
        <?php elseif ($providers === []): ?>
            <p class="muted">Online-Zahlung ist derzeit nicht verfügbar. Bitte überweise den Betrag von
                <strong><?= money($open) ?></strong> per Banküberweisung.</p>
        <?php else: ?>
            <p>Offener Betrag: <strong style="font-size:18px;"><?= money($open) ?></strong></p>
            <?php foreach ($providers as $prov): ?>
                <form method="post" action="/zahlen/<?= e($inv['pay_token']) ?>/start" style="margin-bottom:8px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="provider" value="<?= e($prov) ?>">
                    <button type="submit" class="btn" style="width:100%; justify-content:center;"><?= e(PaymentService::label($prov)) ?> bezahlen</button>
                </form>
            <?php endforeach; ?>
            <p class="help" style="margin-top:12px;">Sichere Zahlung über den jeweiligen Anbieter – deine Zahlungsdaten werden dort eingegeben, nicht bei <?= e($appName) ?>.</p>
        <?php endif; ?>
    </div>
    <p class="powered">Powered by <a href="https://adelmann-solutions.com" target="_blank" rel="noopener">adelmann-solutions.com</a></p>
</div>
</body>
</html>
