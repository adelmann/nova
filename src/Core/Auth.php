<?php

declare(strict_types=1);

namespace Nova\Core;

use Nova\Models\UserRepository;

/**
 * Authentifizierung auf Session-Basis.
 */
final class Auth
{
    private const KEY = 'user_id';

    /**
     * Prüft die Zugangsdaten OHNE eine Session zu starten und gibt den Benutzer
     * zurück (oder null). Nützlich für einen 2FA-Zwischenschritt.
     *
     * @return array<string,mixed>|null
     */
    public static function verify(string $email, string $password): ?array
    {
        $repo = new UserRepository();
        $user = $repo->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Rehash, falls sich der Default-Algorithmus geändert hat.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $repo->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        return $user;
    }

    /** Meldet einen Benutzer anhand seiner ID an (z.B. nach bestandenem 2FA). */
    public static function loginAs(int $userId): void
    {
        Session::regenerate();
        Session::set(self::KEY, $userId);
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = self::verify($email, $password);
        if ($user === null) {
            return false;
        }
        self::loginAs((int) $user['id']);
        return true;
    }

    public static function check(): bool
    {
        return Session::get(self::KEY) !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get(self::KEY);
        return $id === null ? null : (int) $id;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        return (new UserRepository())->find($id);
    }

    /** Rolle des angemeldeten Benutzers (leer, wenn nicht angemeldet). */
    public static function role(): string
    {
        return (string) (self::user()['role'] ?? '');
    }

    /** Darf der angemeldete Benutzer die angegebene Capability? */
    public static function can(?string $cap): bool
    {
        return Acl::can(self::role(), $cap);
    }

    public static function logout(): void
    {
        Session::forget(self::KEY);
        Session::regenerate();
    }
}
