<?php
/**
 * Mova CMS - CSRF Protection
 */

namespace Mova\Security;

use Mova\Core\Bootstrap;

class Csrf
{
    public static function token(): string
    {
        $name = Bootstrap::config('security.csrf_token_name', '_mova_csrf');

        if (empty($_SESSION[$name])) {
            $_SESSION[$name] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$name];
    }

    public static function field(): string
    {
        $name = Bootstrap::config('security.csrf_token_name', '_mova_csrf');
        $token = self::token();
        return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($token) . '">';
    }

    public static function validate(?string $token = null): bool
    {
        $name = Bootstrap::config('security.csrf_token_name', '_mova_csrf');
        $sessionToken = $_SESSION[$name] ?? '';

        if ($token === null) {
            $token = $_POST[$name] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        if (empty($sessionToken) || empty($token)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    public static function regenerate(): void
    {
        $name = Bootstrap::config('security.csrf_token_name', '_mova_csrf');
        $_SESSION[$name] = bin2hex(random_bytes(32));
    }
}
