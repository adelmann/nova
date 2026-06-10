<?php
/** @var string $content */
/** @var string $title */
$current = '/' . trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'), '/');
$appName = $GLOBALS['nova_config']['app_name'] ?? 'Nova';
$navGroups = [
    'Übersicht' => [
        ['/',             'Dashboard',    '◧', 'view_accounting'],
        ['/assistent',    'KI-Assistent', '✦', 'use_assistant'],
    ],
    'Verkauf' => [
        ['/kunden',       'Kunden',       '☺', 'manage_sales'],
        ['/projekte',     'Projekte',     '⚐', 'manage_sales'],
        ['/angebote',     'Angebote',     '✎', 'manage_sales'],
        ['/rechnungen',   'Rechnungen',   '€', 'view_accounting'],
        ['/mahnungen',    'Mahnungen',    '!', 'manage_sales'],
        ['/katalog',      'Leistungskatalog', '≡', 'manage_sales'],
    ],
    'Finanzen' => [
        ['/einnahmen',    'Einnahmen',    '↥', 'view_accounting'],
        ['/ausgaben',     'Ausgaben',     '↧', 'view_accounting'],
        ['/lieferanten',  'Lieferanten',  '☷', 'manage_expenses'],
        ['/belege',       'Belege',       '▤', 'view_accounting'],
        ['/bankimport',   'Bankimport',   '↻', 'manage_expenses'],
        ['/buchhaltung',  'Buchhaltung',  '≣', 'view_accounting'],
    ],
    'Auswertung' => [
        ['/auswertungen', 'Auswertungen', '◈', 'view_accounting'],
        ['/exporte',      'Exporte',      '⇩', 'export'],
        ['/protokoll',    'Protokoll',    '⊟', 'view_accounting'],
    ],
    'System' => [
        ['/konto',         'Konto',         '☻', null],
        ['/einstellungen', 'Einstellungen', '⚙', 'manage_settings'],
        ['/benutzer',      'Benutzer',      '☶', 'manage_users'],
        ['/about',         'Über Nova',     'ⓘ', null],
    ],
];
$novaUpdate = \Nova\Services\UpdateService::cached();
$novaHasUpdate = !empty($novaUpdate['has_update']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '') ?> · <?= e($appName) ?></title>
    <?php // Theme vor dem ersten Paint setzen (kein Flackern). ?>
    <script>
        (function () {
            var t = localStorage.getItem('nova-theme');
            if (!t) { t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
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
<div class="layout">
    <aside class="sidebar">
        <div class="brand"><span class="brand-mark" aria-hidden="true"></span><?= e($appName) ?></div>
        <nav>
            <?php foreach ($navGroups as $group => $items): ?>
                <?php $visible = array_filter($items, static fn ($it) => can($it[3] ?? null)); ?>
                <?php if ($visible === []) { continue; } ?>
                <div class="nav-group">
                    <span class="nav-group-label"><?= e($group) ?></span>
                    <?php foreach ($visible as [$path, $label, $icon]): ?>
                        <a href="<?= e($path) ?>" class="<?= nav_active($path, $current) ?>">
                            <span class="ico"><?= $icon ?></span><?= e($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <?php $u = current_user(); ?>
            <a href="/konto" class="account-link <?= nav_active('/konto', $current) ?>" title="Konto verwalten">
                <span class="account-avatar">☻</span>
                <span class="account-meta">
                    <span class="account-name"><?= e($u['name'] ?? $u['email'] ?? '') ?></span>
                    <span class="account-sub">Konto verwalten</span>
                </span>
                <span class="account-go">›</span>
            </a>
            <form method="post" action="/logout">
                <?= csrf_field() ?>
                <button type="submit" class="btn-link">⇥ Abmelden</button>
            </form>
            <a href="/einstellungen/system" class="version-tag <?= $novaHasUpdate ? 'update' : '' ?>" title="Version &amp; Updates">Nova <?= e(\Nova\Core\Version::CURRENT) ?></a>
        </div>
    </aside>

    <div class="nav-overlay" onclick="document.body.classList.remove('nav-open')"></div>

    <main class="main">
        <header class="topbar">
            <button type="button" class="nav-toggle" aria-label="Menü öffnen" onclick="document.body.classList.toggle('nav-open')">☰</button>
            <a href="/" class="topbar-brand"><span class="brand-mark" aria-hidden="true"></span><?= e($appName) ?></a>
            <h1><?= e($title ?? '') ?></h1>
            <button type="button" class="theme-toggle" aria-label="Hell/Dunkel umschalten" onclick="novaToggleTheme()">
                <span class="theme-icon-dark">☾</span><span class="theme-icon-light">☀</span>
            </button>
        </header>

        <div class="content">
            <?php foreach (flash_messages() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>

            <?php if ($novaHasUpdate): ?>
                <div class="flash flash-warn">
                    Neue Version <strong>Nova <?= e($novaUpdate['latest']) ?></strong> verfügbar.
                    <a href="/einstellungen/system">Jetzt aktualisieren →</a>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </main>
</div>

<?php
// Wichtigste Aktionen als feste Leiste am unteren Rand (nur Mobil).
$bottomNav = [
    ['/',           'Start',      '◧', false, 'view_accounting'],
    ['/belege',     'Belege',     '▤', false, 'view_accounting'],
    ['/belege/neu', 'Beleg',      '＋', true,  'manage_expenses'],
    ['/rechnungen', 'Rechnungen', '€', false, 'view_accounting'],
];
?>
<nav class="bottom-nav" aria-label="Schnellzugriff">
    <?php foreach ($bottomNav as $item): ?>
        <?php if (!can($item[4] ?? null)) { continue; } ?>
        <?php [$path, $label, $icon] = $item; $primary = $item[3] ?? false; ?>
        <a href="<?= e($path) ?>" class="<?= $primary ? 'primary' : nav_active($path, $current) ?>">
            <span class="ico"><?= $icon ?></span><span class="lbl"><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
    <button type="button" class="bn-menu" aria-label="Menü" onclick="document.body.classList.toggle('nav-open')">
        <span class="ico">☰</span><span class="lbl">Menü</span>
    </button>
</nav>

<script src="/assets/js/app.js"></script>
<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/service-worker.js").catch(function(){})})}</script>
</body>
</html>
