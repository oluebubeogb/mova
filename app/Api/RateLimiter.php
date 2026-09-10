<?php
/**
 * Mova API rate limiter (Phase 3)
 */

namespace Mova\Api;

use Mova\Core\Database;

class RateLimiter
{
    public function hit(?int $apiKeyId, string $ip, string $endpoint, int $limit = 120, int $windowSeconds = 60): bool
    {
        $windowStart = date('c', (int) (floor(time() / $windowSeconds) * $windowSeconds));

        $row = Database::fetch(
            "SELECT * FROM api_rate_limits
             WHERE window_start = :w AND endpoint = :e
               AND ((api_key_id IS NOT NULL AND api_key_id = :k) OR (api_key_id IS NULL AND ip_address = :ip))
             LIMIT 1",
            ['w' => $windowStart, 'e' => $endpoint, 'k' => $apiKeyId, 'ip' => $ip]
        );

        if ($row) {
            $hits = (int) $row['hits'] + 1;
            Database::update('api_rate_limits', ['hits' => $hits], 'id = :id', ['id' => $row['id']]);
            return $hits <= $limit;
        }

        Database::insert('api_rate_limits', [
            'api_key_id'   => $apiKeyId,
            'ip_address'   => $ip,
            'endpoint'     => $endpoint,
            'hits'         => 1,
            'window_start' => $windowStart,
        ]);
        return true;
    }
}
