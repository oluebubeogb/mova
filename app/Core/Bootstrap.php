<?php
/**
 * Mova CMS - Bootstrap
 */

namespace Mova\Core;

class Bootstrap
{
    private static bool $booted = false;
    private static array $config = [];

    public static function init(): void
    {
        if (self::$booted) {
            return;
        }

        // Load configuration
        self::$config = require dirname(__DIR__, 2) . '/config/config.php';

        // Error handling
        if (self::$config['debug'] ?? false) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
        }

        // Timezone
        date_default_timezone_set(self::$config['timezone'] ?? 'UTC');

        // Session
        self::startSession();

        // Autoload
        spl_autoload_register([self::class, 'autoload']);

        self::$booted = true;

        // Apply pending schema migrations on installed sites
        try {
            if (class_exists(Schema::class) && Schema::isInstalled()) {
                Schema::migrate();
            }
        } catch (\Throwable $e) {
            // Ignore migration errors during early boot
        }

        // Multi-site resolve + active plugins
        try {
            if (class_exists(Schema::class) && Schema::isInstalled()) {
                if (class_exists(\Mova\Site\SiteService::class)) {
                    (new \Mova\Site\SiteService())->resolveFromHost();
                }
                if (class_exists(\Mova\Plugin\PluginManager::class)) {
                    $pm = new \Mova\Plugin\PluginManager();
                    $pm->bootActive();
                    \Mova\Plugin\PluginManager::doAction('mova.boot');
                }
                if (class_exists(\Mova\Assembly\AssemblyService::class)) {
                    \Mova\Assembly\AssemblyService::registerRenderFilter();
                }
            }
        } catch (\Throwable $e) {
            // isolate extension boot failures
        }
    }

    public static function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return self::$config;
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function path(string $name): string
    {
        $configured = (string) self::config("paths.{$name}", '');
        if ($configured === '') {
            // Sensible defaults relative to project root (folder that contains /app)
            $defaults = [
                'uploads'  => 'mova-uploads',
                'plugins'  => 'mova-plugins',
                'themes'   => 'mova-themes',
                'storage'  => 'storage',
                'cache'    => 'storage/cache',
                'backups'  => 'storage/backups',
                'root'     => '.', // project root
            ];
            $configured = $defaults[$name] ?? '';
        }
        $projectRoot = dirname(__DIR__, 2);
        if ($name === 'root' || $configured === '.') {
            return $projectRoot;
        }
        if ($configured === '') {
            return '';
        }
        // Absolute path already
        if ($configured[0] === '/' || (strlen($configured) > 2 && $configured[1] === ':')) {
            return rtrim($configured, '/\\');
        }
        // Resolve relative to project root (parent of /app)
        return rtrim($projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configured), '/\\');
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = self::config('security.cookie_secure', true);
        // Detect HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_name(self::config('security.session_name', 'mova_session'));
        session_set_cookie_params([
            'lifetime' => self::config('security.session_lifetime', 7200),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps && $secure,
            'httponly' => self::config('security.cookie_httponly', true),
            'samesite' => self::config('security.cookie_samesite', 'Lax'),
        ]);

        session_start();

        // Regenerate ID periodically
        if (!isset($_SESSION['_mova_created'])) {
            $_SESSION['_mova_created'] = time();
        } elseif (time() - $_SESSION['_mova_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_mova_created'] = time();
        }
    }

    private static function autoload(string $class): void
    {
        // Only handle Mova namespace
        if (strpos($class, 'Mova\\') !== 0) {
            return;
        }

        $relative = str_replace('Mova\\', '', $class);
        $relative = str_replace('\\', '/', $relative);
        $file = dirname(__DIR__) . '/' . $relative . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }

    public static function baseUrl(): string
    {
        $configured = self::config('app_url');
        if (!empty($configured)) {
            return rtrim($configured, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }
}
