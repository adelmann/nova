<?php

declare(strict_types=1);

use Nova\Core\DB;
use Nova\Core\Request;
use Nova\Core\Router;
use Nova\Core\Session;
use Nova\Core\View;

// Beim PHP-Entwicklungsserver (php -S) existierende statische Dateien
// direkt ausliefern; nur unbekannte Pfade an den Front-Controller geben.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if (is_file($file)) {
        return false;
    }
}

$config = require dirname(__DIR__) . '/src/bootstrap.php';

// Kerndienste initialisieren.
DB::init($config['db_path']);
View::setViewsPath($config['paths']['views']);
Session::start($config['session_name']);

// Konfiguration für Views/Controller global verfügbar machen.
$GLOBALS['nova_config'] = $config;

// Wartungsmodus während eines laufenden Updates (Flag mit Auto-Ablauf nach 5 Min).
$path = '/' . trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'), '/');
$maintenanceFlag = $config['paths']['storage'] . '/maintenance.flag';
if (is_file($maintenanceFlag) && !str_starts_with($path, '/assets')) {
    $age = time() - (int) (@file_get_contents($maintenanceFlag) ?: 0);
    if ($age >= 0 && $age < 300) {
        http_response_code(503);
        header('Retry-After: 30');
        echo '<!doctype html><meta charset="utf-8"><title>Wartung</title>'
            . '<body style="font-family:system-ui;padding:40px;text-align:center">'
            . '<h1>Kurze Wartung</h1><p>Nova wird gerade aktualisiert. Bitte in einem Moment neu laden.</p></body>';
        exit;
    }
    @unlink($maintenanceFlag); // abgelaufenes Flag entfernen
}

// Erstinstallation: ohne Benutzer in den Setup-Assistenten leiten.
if (!str_starts_with($path, '/setup') && !str_starts_with($path, '/assets')) {
    if (!\Nova\Controllers\SetupController::isInstalled()) {
        \Nova\Core\Response::redirect('/setup');
    }
}

// Routen registrieren und Request abarbeiten.
$router = new Router();
(require dirname(__DIR__) . '/src/routes.php')($router);

$router->dispatch(new Request());
