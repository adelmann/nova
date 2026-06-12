<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Dünne Hülle um die PHP-Session inkl. Flash-Messages.
 */
final class Session
{
    public static function start(string $name, int $lifetime = 0, ?string $savePath = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Eigener Speicherpfad isoliert unsere Sessions von der Garbage-Collection
        // anderer Anwendungen auf Shared Hosting – eine häufige Ursache dafür, dass
        // man trotzdem vorzeitig abgemeldet wird.
        if ($savePath !== null && is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        // Serverseitige Lebensdauer der Session-Daten an die gewünschte Dauer angleichen.
        if ($lifetime > 0) {
            ini_set('session.gc_maxlifetime', (string) $lifetime);
        }

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'path'     => '/',
        ]);
        session_start();

        // Gleitendes Fenster: Bei jeder Aktivität wird das Ablaufdatum des Cookies
        // erneuert, damit aktive Nutzer nicht nach Ablauf des ursprünglichen
        // Login-Fensters herausfallen.
        if ($lifetime > 0 && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie($name, session_id(), [
                'expires'  => time() + $lifetime,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Flash-Message für den nächsten Request setzen. */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }
}
