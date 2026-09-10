<?php
/**
 * Mova CMS - Authentication
 */

namespace Mova\Auth;

use Mova\Core\Bootstrap;
use Mova\Core\Database;
use Mova\Security\Csrf;

class Auth
{
    private static ?array $user = null;

    public static function attempt(string $username, string $password): bool
    {
        // Rate limiting
        if (self::isLockedOut()) {
            return false;
        }

        $user = Database::fetch(
            "SELECT * FROM users WHERE (username = :u OR email = :u) AND status = 'active' LIMIT 1",
            ['u' => $username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            self::recordFailedAttempt($username);
            self::recordLoginHistory(null, $username, false);
            return false;
        }

        // Success
        self::clearFailedAttempts();
        self::login($user);
        self::recordLoginHistory((int) $user['id'], $username, true);
        return true;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['mova_user_id'] = (int) $user['id'];
        $_SESSION['mova_user_role'] = $user['role'];

        Database::update('users', [
            'last_login_at' => date('c'),
            'updated_at'    => date('c'),
        ], 'id = :id', ['id' => $user['id']]);

        self::$user = $user;
        Csrf::regenerate();
    }

    public static function logout(): void
    {
        self::$user = null;
        unset($_SESSION['mova_user_id'], $_SESSION['mova_user_role']);
        session_regenerate_id(true);
        Csrf::regenerate();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = $_SESSION['mova_user_id'] ?? null;
        if (!$id) {
            return null;
        }

        $user = Database::fetch("SELECT * FROM users WHERE id = :id AND status = 'active'", ['id' => $id]);
        if (!$user) {
            self::logout();
            return null;
        }

        unset($user['password']);
        self::$user = $user;
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user['role'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        $role = self::role();
        return $role && in_array($role, $roles, true);
    }

    public static function can(string $permission): bool
    {
        $role = self::role();
        if (!$role) {
            return false;
        }

        $map = [
            'owner'         => ['*'],
            'administrator' => ['manage_content', 'manage_media', 'manage_users', 'manage_settings', 'manage_mail', 'view_insights', 'manage_appearance'],
            'editor'        => ['manage_content', 'manage_media', 'view_insights'],
            'author'        => ['manage_own_content', 'manage_media'],
            'viewer'        => ['view_content'],
        ];

        $perms = $map[$role] ?? [];
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }

    private static function recordLoginHistory(?int $userId, string $username, bool $success): void
    {
        try {
            Database::insert('login_history', [
                'user_id'    => $userId,
                'username'   => $username,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
                'success'    => $success ? 1 : 0,
                'created_at' => date('c'),
            ]);
        } catch (\Throwable $e) {
            // table may not exist yet
        }
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, Bootstrap::config('security.password_algo', PASSWORD_ARGON2ID));
    }

    /** Re-check the logged-in user's password (sensitive actions). */
    public static function verifyPassword(string $password): bool
    {
        $id = self::id();
        if (!$id || $password === '') {
            return false;
        }
        $row = Database::fetch(
            "SELECT password FROM users WHERE id = :id AND status = 'active' LIMIT 1",
            ['id' => $id]
        );
        if (!$row || empty($row['password'])) {
            return false;
        }
        return password_verify($password, (string) $row['password']);
    }

    public static function lockoutRemaining(): int
    {
        if (!self::isLockedOut()) {
            return 0;
        }
        $lockout = (int) Bootstrap::config('security.login_lockout', 3600);
        $ip = self::clientIp();
        try {
            $row = Database::fetch(
                "SELECT MIN(attempted_at) AS first_at FROM login_attempts
                 WHERE ip_address = :ip AND attempted_at > :since",
                ['ip' => $ip, 'since' => date('c', time() - $lockout)]
            );
            if ($row && !empty($row['first_at'])) {
                $unlock = strtotime((string) $row['first_at']) + $lockout;
                return max(0, $unlock - time());
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $lockout;
    }

    private static function isLockedOut(): bool
    {
        // Default: 10 failed attempts per rolling hour, then lock until window slides
        $max = (int) Bootstrap::config('security.login_max_attempts', 10);
        $lockout = (int) Bootstrap::config('security.login_lockout', 3600);
        $ip = self::clientIp();

        $count = Database::count(
            'login_attempts',
            "ip_address = :ip AND attempted_at > :since",
            ['ip' => $ip, 'since' => date('c', time() - $lockout)]
        );

        return $count >= $max;
    }

    private static function recordFailedAttempt(string $username): void
    {
        Database::insert('login_attempts', [
            'ip_address'   => self::clientIp(),
            'username'     => $username,
            'attempted_at' => date('c'),
        ]);
    }

    private static function clearFailedAttempts(): void
    {
        Database::delete('login_attempts', 'ip_address = :ip', ['ip' => self::clientIp()]);
    }

    private static function clientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
