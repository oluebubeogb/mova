<?php
/**
 * Mova CMS - Audit logging
 */

namespace Mova\Security;

use Mova\Core\Database;
use Mova\Auth\Auth;

class Audit
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, $details = null): void
    {
        try {
            Database::insert('audit_logs', [
                'user_id'     => Auth::id(),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'details'     => is_string($details) ? $details : ($details !== null ? json_encode($details) : null),
                'ip_address'  => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at'  => date('c'),
            ]);
        } catch (\Throwable $e) {
            // never break the request
        }
    }
}
