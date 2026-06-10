<?php

declare(strict_types=1);

namespace Nova\Services;

use Nova\Core\Auth;
use Nova\Core\DB;

/**
 * Schreibt unveränderbare Audit-Log-Einträge für alle relevanten Aktionen.
 * Das Datenbank-Trigger-Set verhindert nachträgliches Ändern/Löschen.
 */
final class AuditService
{
    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public static function record(
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $before = null,
        ?array $after = null
    ): void {
        $user      = Auth::user();
        $userLabel = $user['email'] ?? 'system';

        DB::getInstance()->query(
            'INSERT INTO audit_log (user_id, user_label, action, entity_type, entity_id, diff_json)
             VALUES (:uid, :ulabel, :action, :etype, :eid, :diff)',
            [
                'uid'    => $user['id'] ?? null,
                'ulabel' => $userLabel,
                'action' => $action,
                'etype'  => $entityType,
                'eid'    => $entityId,
                'diff'   => json_encode(
                    ['before' => $before, 'after' => $after],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]
        );
    }
}
