<?php
/**
 * Mova — resolve the client IP for rate limiting / lockouts.
 *
 * X-Forwarded-For is only trusted when the direct peer is a private / loopback
 * address (i.e. a reverse proxy such as Coolify/Traefik/nginx in front of PHP).
 * Otherwise a client could spoof the header to dodge lockouts and rate limits.
 */

namespace Mova\Security;

class ClientIp
{
    public static function get(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (self::isPrivate($remote)) {
            $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($xff !== '') {
                // Right-most untrusted hop is the real client
                $hops = array_reverse(array_map('trim', explode(',', $xff)));
                foreach ($hops as $hop) {
                    if (filter_var($hop, FILTER_VALIDATE_IP) && !self::isPrivate($hop)) {
                        return $hop;
                    }
                }
            }
            $real = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? '');
            if ($real !== '' && filter_var($real, FILTER_VALIDATE_IP)) {
                return $real;
            }
        }
        return $remote;
    }

    public static function isPrivate(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
