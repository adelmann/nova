<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * CSRF-Token-Verwaltung. Ein Token pro Session, gegen Timing-Angriffe per
 * hash_equals verglichen.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    public static function verify(?string $token): bool
    {
        $expected = Session::get(self::KEY);
        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
