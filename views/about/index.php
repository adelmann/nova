<?php
/** @var string $version */
/** @var string $repo */
/** @var array<string,mixed>|null $update */
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
$repoUrl = $repo !== '' ? 'https://github.com/' . $repo : '';
?>
<div class="panel">
    <div style="display:flex; align-items:center; gap:14px;">
        <span class="brand-mark" aria-hidden="true" style="width:48px; height:48px;"></span>
        <div>
            <h2 style="margin:0;"><?= e($appName) ?> <span class="muted">v<?= e($version) ?></span></h2>
            <p class="muted" style="margin:2px 0 0;">Business- &amp; Buchhaltungstool für Kleingewerbe (EÜR)</p>
        </div>
    </div>

    <p style="margin-top:16px;">
        Nova verwaltet Kunden, Projekte, Angebote, Rechnungen, Ausgaben, Belege und die
        Einnahmen-Überschuss-Rechnung – reines PHP/SQLite, ohne Cloud-Zwang, selbst gehostet.
    </p>

    <dl class="detail" style="margin-top:8px;">
        <dt>Version</dt><dd>Nova <?= e($version) ?>
            <?php if (!empty($update['has_update'])): ?>
                · <a href="/einstellungen">Update auf <?= e($update['latest']) ?> verfügbar</a>
            <?php endif; ?>
        </dd>
        <dt>Projektseite</dt><dd><a href="https://adelmann.github.io/nova/" target="_blank" rel="noopener">adelmann.github.io/nova</a> · <a href="https://adelmann.github.io/nova/anleitung.html" target="_blank" rel="noopener">Anleitung</a></dd>
        <?php if ($repoUrl !== ''): ?>
            <dt>Quellcode</dt><dd><a href="<?= e($repoUrl) ?>" target="_blank" rel="noopener"><?= e($repo) ?></a></dd>
        <?php endif; ?>
        <dt>Lizenz</dt><dd>MIT © 2026 Adelmann Solutions</dd>
    </dl>
</div>

<div class="panel">
    <h2>Rechtliches</h2>
    <?= partial('partials/_disclaimer') ?>
    <p class="help" style="margin-top:12px;">
        Steuerlicher Hinweis: Nova unterstützt die Einnahmen-Überschuss-Rechnung nach § 4 Abs. 3 EStG
        und die Kleinunternehmerregelung nach § 19 UStG, ersetzt aber keine Steuerberatung.
    </p>
</div>

<p class="powered">Powered by <a href="https://adelmann-solutions.com" target="_blank" rel="noopener">adelmann-solutions.com</a></p>
