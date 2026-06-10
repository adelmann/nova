<?php

declare(strict_types=1);

namespace Nova\Core;

/**
 * Rollen- und Rechtemodell (Access Control List).
 *
 * Rollen → Capabilities (Fähigkeiten). Routen und Views fragen Capabilities ab,
 * nicht Rollen direkt – so liegt die Logik an einer Stelle.
 */
final class Acl
{
    /** Anzeigenamen der Rollen. */
    public const ROLES = [
        'admin'      => 'Inhaber (Vollzugriff)',
        'staff'      => 'Mitarbeiter',
        'accountant' => 'Steuerberater (nur lesen)',
    ];

    /** @var array<string,array<int,string>> Rolle → Capabilities ('*' = alle). */
    private const MAP = [
        'admin'      => ['*'],
        'staff'      => ['manage_sales', 'manage_expenses', 'view_accounting', 'export', 'use_assistant'],
        'accountant' => ['view_accounting', 'export'],
    ];

    public static function can(string $role, ?string $cap): bool
    {
        if ($cap === null || $cap === '') {
            return true; // keine besondere Berechtigung nötig
        }
        $caps = self::MAP[$role] ?? [];
        return in_array('*', $caps, true) || in_array($cap, $caps, true);
    }

    public static function isRole(string $role): bool
    {
        return isset(self::ROLES[$role]);
    }

    public static function label(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }
}
