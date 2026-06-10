<?php

declare(strict_types=1);

/**
 * Zentrale Konfiguration für Nova.
 *
 * Werte können per Umgebungsvariable überschrieben werden (z.B. auf dem
 * Produktivserver), haben aber sinnvolle Defaults für den lokalen Betrieb.
 */

$root = __DIR__;

return [
    // Anzeigename und Basis-URL der Anwendung.
    'app_name'    => getenv('NOVA_APP_NAME') ?: 'Nova',
    'app_url'     => getenv('NOVA_APP_URL') ?: '',
    'environment' => getenv('NOVA_ENV') ?: 'production',

    // GitHub-Repository für die Update-Prüfung (owner/repo).
    'github_repo' => getenv('NOVA_GITHUB_REPO') ?: 'adelmann/nova',

    // Datenbank: ausschließlich SQLite-Datei.
    'db_path' => getenv('NOVA_DB_PATH') ?: $root . '/storage/db/nova.sqlite',

    // Verzeichnisse (außerhalb des Web-Roots public/).
    'paths' => [
        'root'       => $root,
        'storage'    => $root . '/storage',
        'receipts'   => $root . '/storage/receipts',
        'invoices'   => $root . '/storage/invoices',
        'backups'    => $root . '/storage/backups',
        'migrations' => $root . '/migrations',
        'views'      => $root . '/views',
        'logos'      => $root . '/storage/logos',
    ],

    // Upload-Beschränkungen.
    'uploads' => [
        'max_bytes'     => 10 * 1024 * 1024, // 10 MB
        'allowed_mimes' => [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
        ],
    ],

    // Session.
    'session_name' => 'nova_session',
];
