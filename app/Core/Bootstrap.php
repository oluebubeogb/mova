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

        // Fallbacks for hosts without ext-mbstring (no-ops when it is loaded).
        if (!extension_loaded('mbstring') && is_file(__DIR__ . '/polyfills.php')) {
            require_once __DIR__ . '/polyfills.php';
        }

        // Load configuration
        $cfgFile = dirname(__DIR__, 2) . '/config/config.php';
        if (!is_file($cfgFile)) {
            // Usually: the site folder was replaced by an update package (which never ships
            // config/config.php). Say so clearly instead of a blank fatal 500.
            $msg = 'mova.boot config/config.php is missing - restore it from your backup (update packages never include it).';
            error_log($msg);
            if (PHP_SAPI !== 'cli') {
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/plain; charset=UTF-8');
                }
                echo "Mova is not configured: config/config.php is missing.\n"
                   . "If you just uploaded an update, restore config/config.php (and storage/) from your previous copy.\n";
                exit;
            }
            throw new \RuntimeException('config/config.php is missing');
        }
        self::$config = require $cfgFile;

        // Error handling: fatals/uncaught exceptions are logged to storage/logs/mova-errors.log
        // and stderr (docker logs) with a reference id; visitors get a friendly 500 page.
        // (Before 1.2.2 production used error_reporting(0): every fatal was a blank, unlogged 500.)
        $movaRoot = dirname(__DIR__, 2);
        if (!class_exists(ErrorHandler::class, false) && is_file(__DIR__ . '/ErrorHandler.php')) {
            require_once __DIR__ . '/ErrorHandler.php';
        }
        if (class_exists(ErrorHandler::class, false)) {
            ErrorHandler::register((bool) (self::$config['debug'] ?? false), $movaRoot, (string) (self::$config['paths']['logs'] ?? ''));
        } elseif (self::$config['debug'] ?? false) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            ini_set('display_errors', '0');
        }

        // Timezone
        date_default_timezone_set(self::$config['timezone'] ?? 'UTC');

        // Session
        self::startSession();

        // Autoload
        spl_autoload_register([self::class, 'autoload']);

        self::$booted = true;

        // New code deployed in place? Reset opcache so old and new files are never mixed
        // (validate_timestamps=0 in the Docker image), then re-serve the GET once.
        if (class_exists(Runtime::class)) {
            Runtime::syncCode();
        }

        // Apply pending schema migrations once per schema/code change (idempotent). Failures are
        // logged with a reference id and retried on the next request - no longer swallowed.
        try {
            if (defined('MOVA_NO_AUTO_MIGRATE')) {
                // bin/mova-doctor.php reports first and migrates only with --migrate.
            } elseif (class_exists(Runtime::class)) {
                Runtime::migrateIfNeeded();
            } elseif (class_exists(Schema::class) && Schema::isInstalled()) {
                Schema::migrate();
            }
        } catch (\Throwable $e) {
            if (class_exists(ErrorHandler::class, false)) {
                ErrorHandler::log('migration', $e->getMessage(), $e->getFile(), $e->getLine(), get_class($e));
            }
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
                if (class_exists(\Mova\Gallery\GalleryService::class)) {
                    \Mova\Gallery\GalleryService::registerShortcodes();
                }
                if (class_exists(\Mova\Feedback\FeedbackService::class)) {
                    \Mova\Feedback\FeedbackService::registerShortcodes();
                    \Mova\Feedback\FeedbackService::ensureDefaultContactForm();
                }
                if (class_exists(\Mova\Blog\BlogService::class)) {
                    \Mova\Blog\BlogService::registerShortcodes();
                }
                // Markdown → HTML only for non-HTML bodies (before shortcodes)
                if (class_exists(\Mova\Content\Markdown::class)) {
                    \Mova\Content\Markdown::registerRenderFilter();
                }
            }
        } catch (\Throwable $e) {
            // isolate extension boot failures, but leave a trace
            if (class_exists(ErrorHandler::class, false)) {
                ErrorHandler::log('boot', $e->getMessage(), $e->getFile(), $e->getLine(), get_class($e));
            }
        }
    }

    /**
     * Running Mova version. The code ships a VERSION file so an upgraded deploy reports
     * its real version even when config/config.php (not part of a deploy) still carries
     * an older app_version. Returns the higher of the two.
     */
    public static function version(): string
    {
        static $code = null;
        if ($code === null) {
            $f = dirname(__DIR__, 2) . '/VERSION';
            $code = is_file($f) ? trim((string) @file_get_contents($f)) : '';
            if (!preg_match('/^\d+\.\d+(\.\d+)?([\-+][0-9A-Za-z.\-]+)?$/', $code)) {
                $code = '';
            }
        }
        $cfg = (string) (self::$config['app_version'] ?? '');
        if ($code === '') {
            return $cfg !== '' ? $cfg : '1.0.0';
        }
        if ($cfg === '') {
            return $code;
        }
        return version_compare($code, $cfg, '>=') ? $code : $cfg;
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
        // Use 0 lifetime so the cookie is a pure session cookie; we enforce
        // inactivity ourselves via _mova_last_activity (sliding window).
        session_set_cookie_params([
            'lifetime' => 0,
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

        // Sliding inactivity window for authenticated users (checked in Auth)
        if (isset($_SESSION['mova_user_id'])) {
            $lifetime = (int) self::config('security.session_lifetime', 7200);
            $last = (int) ($_SESSION['_mova_last_activity'] ?? 0);
            if ($last > 0 && (time() - $last) > $lifetime) {
                // Mark as timed out; Auth / requireAuth will finish logout + redirect
                $_SESSION['_mova_timed_out'] = 1;
                unset($_SESSION['mova_user_id'], $_SESSION['mova_user_role'], $_SESSION['_mova_last_activity']);
            } else {
                $_SESSION['_mova_last_activity'] = time();
            }
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

        // Detect HTTPS the same way as session cookies / HSTS (proxy-aware)
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }
}
