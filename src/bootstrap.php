<?php

declare(strict_types=1);

/**
 * Bootstrap: PSR-4-Autoloader (ohne Composer), Konfiguration laden,
 * Fehlerbehandlung und gemeinsame Dienste bereitstellen.
 */

// --- Autoloader für den Namespace Nova\ => src/ ---------------------------
spl_autoload_register(static function (string $class): void {
    $prefix  = 'Nova\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// --- Optionaler Composer-Autoloader (für Dompdf), falls vorhanden ---------
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

// --- Globale Helfer-Funktionen für Views ----------------------------------
require __DIR__ . '/helpers.php';

// --- Konfiguration --------------------------------------------------------
$config = require dirname(__DIR__) . '/config.php';

// Sicherstellen, dass Speicherverzeichnisse existieren.
foreach (['storage', 'receipts', 'invoices', 'backups', 'logos'] as $key) {
    $dir = $config['paths'][$key] ?? null;
    if ($dir !== null && !is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
$dbDir = dirname($config['db_path']);
if (!is_dir($dbDir)) {
    @mkdir($dbDir, 0775, true);
}

// --- Fehlerbehandlung -----------------------------------------------------
if (($config['environment'] ?? 'production') === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

return $config;
