<?php

declare(strict_types=1);

/**
 * Setzt das Passwort eines Benutzers per CLI zurück – Notfall-/Fallback-Weg,
 * falls kein E-Mail-Versand konfiguriert ist oder man ausgesperrt ist.
 *
 * Aufruf:  php bin/reset-password.php deine@mail.de "NeuesPasswort123"
 */

use Nova\Core\DB;
use Nova\Models\UserRepository;

$config = require dirname(__DIR__) . '/src/bootstrap.php';
DB::init($config['db_path']);

$email    = $argv[1] ?? null;
$password = $argv[2] ?? null;

if ($email === null) {
    $email = trim((string) readline('E-Mail des Benutzers: '));
}
if ($password === null) {
    $password = trim((string) readline('Neues Passwort (min. 8 Zeichen): '));
}

if ($email === '' || strlen((string) $password) < 8) {
    fwrite(STDERR, "Abbruch: gültige E-Mail und Passwort (min. 8 Zeichen) erforderlich.\n");
    exit(1);
}

$users = new UserRepository();
$user  = $users->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "Abbruch: kein Benutzer mit dieser E-Mail.\n");
    exit(1);
}

$users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
echo "Passwort für {$email} wurde zurückgesetzt.\n";
