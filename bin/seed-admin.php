<?php

declare(strict_types=1);

/**
 * Legt den ersten Admin-Benutzer an und initialisiert die
 * company_settings-Zeile.
 *
 * Aufruf interaktiv:   php bin/seed-admin.php
 * Aufruf direkt:       php bin/seed-admin.php admin@example.com "Geheim123" "Andreas"
 */

use Nova\Core\DB;
use Nova\Models\UserRepository;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
DB::init($config['db_path']);
$db = DB::getInstance();

// company_settings sicherstellen (genau eine Zeile mit id = 1).
$exists = (int) $db->fetchColumn('SELECT COUNT(*) FROM company_settings WHERE id = 1');
if ($exists === 0) {
    $db->query('INSERT INTO company_settings (id) VALUES (1)');
    echo "» company_settings initialisiert.\n";
}

// Argumente oder interaktive Eingabe.
$email    = $argv[1] ?? null;
$password = $argv[2] ?? null;
$name     = $argv[3] ?? 'Admin';

if ($email === null) {
    $email = trim((string) readline('E-Mail: '));
}
if ($password === null) {
    $password = trim((string) readline('Passwort (min. 8 Zeichen): '));
}

if ($email === '' || strlen($password) < 8) {
    fwrite(STDERR, "Abbruch: gültige E-Mail und Passwort (min. 8 Zeichen) erforderlich.\n");
    exit(1);
}

$repo = new UserRepository();
if ($repo->findByEmail($email) !== null) {
    fwrite(STDERR, "Abbruch: Benutzer mit dieser E-Mail existiert bereits.\n");
    exit(1);
}

$repo->create($email, password_hash($password, PASSWORD_DEFAULT), $name);
echo "Admin-Benutzer '{$email}' angelegt.\n";
