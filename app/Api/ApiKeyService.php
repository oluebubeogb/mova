<?php
/**
 * Mova — API key management (V2 Phase 1)
 */

namespace Mova\Api;

use Mova\Core\Database;

class ApiKeyService
{
    public function all(): array
    {
        return Database::fetchAll(
            "SELECT id, name, key_prefix, scopes, last_used_at, expires_at, status, created_by, created_at
             FROM api_keys ORDER BY created_at DESC"
        );
    }

    public function create(string $name, array $scopes = ['read'], ?int $createdBy = null, ?string $expiresAt = null): array
    {
        $raw = 'mova_' . bin2hex(random_bytes(24));
        $prefix = substr($raw, 0, 12);
        $hash = password_hash($raw, PASSWORD_ARGON2ID);

        $id = Database::insert('api_keys', [
            'name'       => trim($name) ?: 'API Key',
            'key_hash'   => $hash,
            'key_prefix' => $prefix,
            'scopes'     => json_encode($scopes),
            'expires_at' => $expiresAt,
            'status'     => 'active',
            'created_by' => $createdBy,
            'created_at' => date('c'),
        ]);

        return [
            'id'     => $id,
            'key'    => $raw, // only shown once
            'prefix' => $prefix,
            'name'   => $name,
            'scopes' => $scopes,
        ];
    }

    public function revoke(int $id): bool
    {
        return Database::update('api_keys', ['status' => 'revoked'], 'id = :id', ['id' => $id]) > 0;
    }

    public function delete(int $id): bool
    {
        return Database::delete('api_keys', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Validate Bearer token. Returns key row or null.
     */
    public function authenticate(?string $token): ?array
    {
        if (!$token || strlen($token) < 16) {
            return null;
        }

        $prefix = substr($token, 0, 12);
        $candidates = Database::fetchAll(
            "SELECT * FROM api_keys WHERE key_prefix = :p AND status = 'active'",
            ['p' => $prefix]
        );

        foreach ($candidates as $row) {
            if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
                continue;
            }
            if (password_verify($token, $row['key_hash'])) {
                Database::update('api_keys', ['last_used_at' => date('c')], 'id = :id', ['id' => $row['id']]);
                $row['scopes'] = json_decode($row['scopes'] ?? '[]', true) ?: [];
                return $row;
            }
        }

        return null;
    }

    public function hasScope(array $key, string $scope): bool
    {
        $scopes = $key['scopes'] ?? [];
        if (in_array('*', $scopes, true) || in_array('admin', $scopes, true)) {
            return true;
        }
        return in_array($scope, $scopes, true);
    }
}
