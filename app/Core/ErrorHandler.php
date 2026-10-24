<?php
/**
 * Mova CMS - production-safe error handling.
 *
 * - Fatal errors and uncaught exceptions are written to storage/logs/mova-errors.log AND to
 *   stderr (so `docker compose logs` shows them), each with a short reference id.
 * - Visitors get a friendly 500 page with that reference id; with debug on, the page also shows
 *   the message and file:line.
 * - A fatal that looks like stale compiled code ("Call to undefined function/method",
 *   "Class not found") after an in-place upgrade triggers an opcache reset, so a reload fixes it.
 *
 * This file must stay dependency-free: it runs when the rest of the app may be broken.
 */

namespace Mova\Core;

class ErrorHandler
{
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private static bool $registered = false;
    private static bool $debug = false;
    private static string $root = '';
    private static ?string $logDir = null;
    private static bool $handled = false;

    public static function register(bool $debug, string $root, ?string $logDir = null): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        self::$debug = $debug;
        self::$root = rtrim($root, '/\\');
        self::$logDir = $logDir ?: null;

        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            // Never show errors to visitors, but keep fatals reportable (1.2.1 used
            // error_reporting(0), which turned every fatal into a blank, unlogged 500).
            error_reporting(self::FATAL);
            ini_set('display_errors', '0');
        }
        ini_set('log_errors', '1');

        set_exception_handler([self::class, 'onException']);
        register_shutdown_function([self::class, 'onShutdown']);
    }

    public static function onException(\Throwable $e): void
    {
        self::$handled = true;
        $ref = self::log('uncaught', $e->getMessage(), $e->getFile(), $e->getLine(), get_class($e), $e->getTraceAsString());
        self::maybeResetStaleCode($e->getMessage());
        self::render($ref, get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
    }

    public static function onShutdown(): void
    {
        if (self::$handled) {
            return;
        }
        $err = error_get_last();
        if (!$err || !($err['type'] & self::FATAL)) {
            return;
        }
        self::$handled = true;
        $msg = (string) $err['message'];
        // "Uncaught Error: X in file:line\nStack trace:..." -> keep the first line for the page.
        $first = strtok($msg, "\n");
        $ref = self::log('fatal', $msg, (string) $err['file'], (int) $err['line'], 'E' . $err['type']);
        self::maybeResetStaleCode($msg);
        self::render($ref, $first !== false ? $first : $msg, (string) $err['file'], (int) $err['line']);
    }

    /**
     * Log a non-fatal problem that was caught and degraded (e.g. an optional editor panel).
     * Returns the reference id.
     */
    public static function report(string $context, \Throwable $e): string
    {
        $ref = self::log('degraded:' . $context, $e->getMessage(), $e->getFile(), $e->getLine(), get_class($e));
        self::maybeResetStaleCode($e->getMessage());
        return $ref;
    }

    /** Write one entry to storage/logs/mova-errors.log and stderr. Returns the reference id. */
    public static function log(string $kind, string $message, string $file = '', int $line = 0, string $class = '', string $trace = ''): string
    {
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? ($_SERVER['argv'][0] ?? '');
        // Never log query strings (they can carry tokens) - path only.
        $uri = (string) strtok((string) $uri, '?');
        $file = self::rel($file);
        $message = self::scrub($message);
        $lineTxt = sprintf(
            '[%s] mova.%s ref=%s %s %s :: %s%s%s',
            gmdate('Y-m-d\TH:i:s\Z'),
            $kind,
            $ref,
            $method,
            $uri,
            $class !== '' ? $class . ': ' : '',
            str_replace(["\r", "\n"], ' | ', $message),
            $file !== '' ? ' in ' . $file . ':' . $line : ''
        );
        $entry = $lineTxt . "\n";
        if ($trace !== '') {
            $entry .= '    ' . str_replace("\n", "\n    ", self::scrub(str_replace(self::$root . '/', '', $trace))) . "\n";
        }
        $dir = self::logDir();
        if ($dir !== '') {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($dir . '/mova-errors.log', $entry, FILE_APPEND | LOCK_EX);
        }
        // stderr -> php-fpm worker output -> docker logs (catch_workers_output=yes in the
        // official image). On the CLI this is the terminal.
        $fh = @fopen('php://stderr', 'ab');
        if ($fh) {
            @fwrite($fh, $lineTxt . "\n");
            @fclose($fh);
        }
        return $ref;
    }

    public static function logDir(): string
    {
        if (self::$logDir !== null && self::$logDir !== '') {
            return rtrim(self::$logDir, '/\\');
        }
        return self::$root !== '' ? self::$root . '/storage/logs' : '';
    }

    /** Undefined function/method/class right after an upgrade = stale opcache. Clear it. */
    private static function maybeResetStaleCode(string $msg): void
    {
        if (!preg_match('/Call to undefined (function|method)|Class "?[^"]*"? not found|Cannot redeclare|Interface "?[^"]*"? not found|Too few arguments|Unknown named parameter/i', $msg)) {
            return;
        }
        if (function_exists('opcache_get_status') && function_exists('opcache_reset')) {
            $ok = @opcache_reset();
            self::log('opcache', $ok ? 'opcache_reset() after a stale-code fatal; reload the page' : 'opcache_reset() unavailable (opcache.restrict_api?) - restart PHP-FPM');
        }
    }

    private static function render(string $ref, string $message, string $file, int $line): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        // Drop any half-rendered page.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
            header('X-Mova-Error-Ref: ' . $ref);
        }
        $isHq = strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/hq') === 0;
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $detail = '';
        if (self::$debug) {
            $detail = '<pre style="white-space:pre-wrap;background:#f6f7f9;border:1px solid #e5e7eb;border-radius:8px;padding:12px;font-size:12px;text-align:left;overflow:auto">'
                . $h(self::scrub($message)) . "\n" . $h(self::rel($file)) . ':' . $line . '</pre>';
        }
        $adminHint = $isHq
            ? '<p style="color:#6b7280;font-size:14px">Administrators: the full error is in <code>storage/logs/mova-errors.log</code> (search for the reference) and in the container/PHP error log. Run <code>php bin/mova-doctor.php</code> on the server for a health check. If this started right after an update, restart PHP-FPM (or the container) to clear the code cache.</p>'
            : '';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>Something went wrong</title></head>'
            . '<body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f5f5f7;color:#111827;margin:0">'
            . '<main style="max-width:560px;margin:12vh auto;padding:32px;background:#fff;border:1px solid #e5e7eb;border-radius:14px;text-align:center">'
            . '<h1 style="font-size:22px;margin:0 0 8px">Something went wrong</h1>'
            . '<p style="margin:0 0 16px">This page hit an unexpected error. Please reload; if it keeps happening, share this reference with your site administrator.</p>'
            . '<p style="font:600 15px ui-monospace,Menlo,monospace;background:#f3f4f6;display:inline-block;padding:6px 12px;border-radius:8px">Ref ' . $h($ref) . '</p>'
            . $adminHint . $detail
            . '<p style="margin-top:20px"><a href="javascript:location.reload()" style="color:#2563eb">Reload</a>'
            . ($isHq ? ' &middot; <a href="/hq" style="color:#2563eb">Back to HQ</a>' : ' &middot; <a href="/" style="color:#2563eb">Home</a>')
            . '</p></main></body></html>';
    }

    private static function rel(string $file): string
    {
        if (self::$root !== '' && strpos($file, self::$root . '/') === 0) {
            return substr($file, strlen(self::$root) + 1);
        }
        return $file;
    }

    /** Mask anything that looks like a credential before it reaches a log or a page. */
    private static function scrub(string $s): string
    {
        $s = preg_replace('/\b(sk|pk|rk|mova|key|tok)[-_][A-Za-z0-9_\-]{12,}/', '$1-***', $s) ?? $s;
        $s = preg_replace('/(Bearer\s+)[A-Za-z0-9._\-]+/i', '$1***', $s) ?? $s;
        $s = preg_replace('/((?:api[_-]?key|password|secret|token)["\']?\s*[=:]\s*["\']?)[^\s"\',&]+/i', '$1***', $s) ?? $s;
        return $s;
    }
}
