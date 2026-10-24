<?php
/**
 * Mova CMS - upgrade-safe runtime housekeeping (1.2.2).
 *
 * 1. Code fingerprint + opcache: production PHP-FPM images run with
 *    opcache.validate_timestamps=0, so after files are replaced in place (volume mount, unzip,
 *    docker cp) the pool keeps serving the OLD compiled copy of files it already cached while
 *    compiling NEW copies of files it had not loaded yet. Old helpers + new views =
 *    "Call to undefined function hqTimeTag()" = HTTP 500. On the first request after the code
 *    changes we reset opcache and re-issue the GET once, so every file is recompiled together.
 *
 * 2. Schema version gate: migrations are idempotent. They now run once per code/schema change
 *    instead of on every request, and failures are logged with a reference id (they were
 *    silently swallowed before) and retried on the next request until they succeed.
 */

namespace Mova\Core;

class Runtime
{
    /** Bump when Schema::migrate() gains a phase. */
    public const SCHEMA_VERSION = 14;

    private const STATE_KEY = 'mova_schema_state';

    /** Files whose change means "new code deployed". Stat-only, cheap. */
    private const FINGERPRINT_FILES = [
        'VERSION',
        'app/Core/Bootstrap.php',
        'app/Core/Schema.php',
        'public/hq/helpers.php',
        'public/hq/index.php',
        'public/index.php',
    ];

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function fingerprint(): string
    {
        $root = self::root();
        $parts = [];
        foreach (self::FINGERPRINT_FILES as $rel) {
            $f = $root . '/' . $rel;
            $parts[] = $rel . ':' . (is_file($f) ? (filesize($f) . '@' . filemtime($f)) : '-');
        }
        $v = is_file($root . '/VERSION') ? trim((string) @file_get_contents($root . '/VERSION')) : '';
        return $v . '|' . substr(sha1(implode(';', $parts)), 0, 16);
    }

    private static function markerFile(): string
    {
        $cache = Bootstrap::path('cache');
        if ($cache === '') {
            $cache = self::root() . '/storage/cache';
        }
        return rtrim($cache, '/\\') . '/.mova-code-fingerprint';
    }

    /**
     * Detect a code change since the last request. If opcache is active, reset it and (for a
     * plain GET in a web request) redirect to the same URL once so the page is served entirely
     * from fresh code. Never throws.
     */
    public static function syncCode(): void
    {
        // The marker belongs to the web pool's opcache. CLI processes (ai-worker, doctor) have
        // their own cache and must not "consume" the change before the web pool sees it.
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }
        try {
            clearstatcache();
            $fp = self::fingerprint();
            $marker = self::markerFile();
            $prev = is_file($marker) ? trim((string) @file_get_contents($marker)) : '';
            if ($prev === $fp) {
                return;
            }
            $dir = dirname($marker);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $written = @file_put_contents($marker, $fp, LOCK_EX) !== false;
            $reset = false;
            if (self::opcacheActive() && function_exists('opcache_reset')) {
                $reset = (bool) @opcache_reset();
            }
            if ($prev !== '') {
                ErrorHandler::log('upgrade', 'Code changed (' . $prev . ' -> ' . $fp . '); opcache ' . ($reset ? 'reset' : (self::opcacheActive() ? 'reset FAILED - restart PHP-FPM' : 'not active')));
            }
            // Re-run the request on fresh code. Only for idempotent GETs, only once (the marker
            // is already written, so the next request will not loop), only on the web.
            if ($reset && $written && $prev !== '' && PHP_SAPI !== 'cli'
                && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && !headers_sent()) {
                header('Cache-Control: no-store');
                header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 302);
                exit;
            }
        } catch (\Throwable $e) {
            // Housekeeping must never take the site down.
        }
    }

    public static function opcacheActive(): bool
    {
        if (!function_exists('opcache_get_status')) {
            return false;
        }
        $st = @opcache_get_status(false);
        return is_array($st) && !empty($st['opcache_enabled']);
    }

    public static function expectedState(): string
    {
        return self::SCHEMA_VERSION . '@' . Bootstrap::version() . '#' . self::fingerprint();
    }

    public static function storedState(): string
    {
        try {
            $row = Database::fetch('SELECT setting_value FROM settings WHERE setting_key = ?', [self::STATE_KEY]);
            return (string) ($row['setting_value'] ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function migrationsPending(): bool
    {
        return self::storedState() !== self::expectedState();
    }

    /**
     * Run idempotent migrations when the schema/code state changed. Logs and records failures
     * (storage/cache/.mova-migration-error.json) instead of hiding them; retried next request.
     *
     * @return bool true when the schema is current
     */
    public static function migrateIfNeeded(bool $force = false): bool
    {
        if (!Schema::isInstalled()) {
            return true;
        }
        $expected = self::expectedState();
        if (!$force && self::storedState() === $expected) {
            return true;
        }
        $errFile = dirname(self::markerFile()) . '/.mova-migration-error.json';
        try {
            Schema::migrate();
            $now = gmdate('Y-m-d H:i:s');
            Database::query(
                'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at',
                [self::STATE_KEY, $expected, $now]
            );
            if (is_file($errFile)) {
                @unlink($errFile);
            }
            return true;
        } catch (\Throwable $e) {
            $ref = ErrorHandler::log('migration', $e->getMessage(), $e->getFile(), $e->getLine(), get_class($e));
            @file_put_contents($errFile, json_encode([
                'ref' => $ref,
                'time' => gmdate('c'),
                'message' => mb_substr_safe($e->getMessage(), 300),
            ]), LOCK_EX);
            return false;
        }
    }

    public static function lastMigrationError(): ?array
    {
        $errFile = dirname(self::markerFile()) . '/.mova-migration-error.json';
        if (!is_file($errFile)) {
            return null;
        }
        $d = json_decode((string) @file_get_contents($errFile), true);
        return is_array($d) ? $d : null;
    }

    /**
     * Environment diagnostics shared by bin/mova-doctor.php and HQ -> System health.
     * Each item: id, label, status (ok|warn|error), detail. Never includes secrets.
     */
    public static function diagnostics(): array
    {
        $items = [];
        $add = static function (string $id, string $label, string $status, string $detail) use (&$items): void {
            $items[] = ['id' => $id, 'label' => $label, 'status' => $status, 'detail' => $detail];
        };

        $add('php', 'PHP version', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'error',
            PHP_VERSION . ' (' . PHP_SAPI . '); Mova needs 8.1+');

        $exts = [
            'pdo_sqlite' => [true, 'database'],
            'mbstring' => [true, 'text handling (falls back, but slower/less accurate)'],
            'json' => [true, 'API / settings'],
            'intl' => [false, 'Unicode normalisation, slugs'],
            'gd' => [false, 'image resizing / variants'],
            'zip' => [false, 'backups, site packs, plugin installs'],
            'curl' => [false, 'AI, webhooks, update checks (falls back to streams)'],
            'openssl' => [false, 'HTTPS calls, mail'],
            'fileinfo' => [false, 'upload type detection'],
        ];
        foreach ($exts as $ext => [$required, $why]) {
            $ok = extension_loaded($ext);
            $add('ext_' . $ext, 'Extension ' . $ext, $ok ? 'ok' : ($required ? 'error' : 'warn'), ($ok ? 'loaded' : 'MISSING') . ' - ' . $why);
        }

        // Opcache: validate_timestamps=0 needs a reset after every in-place upgrade.
        if (self::opcacheActive()) {
            $vt = (string) ini_get('opcache.validate_timestamps');
            $stale = ($vt === '0' || $vt === '' || strtolower($vt) === 'off');
            $add('opcache', 'OPcache', $stale ? 'warn' : 'ok', $stale
                ? 'enabled, validate_timestamps=0: after replacing files, restart PHP-FPM (or the container). Mova 1.2.2+ also resets it automatically on the first request after a code change.'
                : 'enabled, validate_timestamps=1 (revalidate_freq=' . ini_get('opcache.revalidate_freq') . 's)');
        } else {
            $add('opcache', 'OPcache', 'ok', PHP_SAPI === 'cli' ? 'not active in CLI (normal; the web pool has its own cache)' : 'not active');
        }

        // Writable dirs
        $root = self::root();
        foreach (['storage', 'storage/cache', 'storage/logs', 'storage/backups', 'public/mova-uploads'] as $rel) {
            $p = $root . '/' . $rel;
            $ok = is_dir($p) && is_writable($p);
            $add('dir_' . str_replace('/', '_', $rel), 'Writable ' . $rel, $ok ? 'ok' : ($rel === 'storage/backups' ? 'warn' : 'error'),
                $ok ? 'writable' : (is_dir($p) ? 'NOT writable by ' . self::whoami() : 'missing'));
        }

        // SQLite files (db, -wal, -shm) must be writable by the PHP user, e.g. not root-owned in Docker.
        $dbCfg = (array) (Bootstrap::config('database') ?? []);
        $dbFile = (string) ($dbCfg['path'] ?? '');
        if ($dbFile !== '' && is_file($dbFile)) {
            $bad = [];
            foreach (['', '-wal', '-shm', '-journal'] as $sfx) {
                if (is_file($dbFile . $sfx) && !is_writable($dbFile . $sfx)) {
                    $bad[] = basename($dbFile . $sfx);
                }
            }
            $add('db_files', 'Database files', $bad ? 'error' : 'ok', $bad
                ? 'NOT writable by ' . self::whoami() . ': ' . implode(', ', $bad) . ' (fix ownership, e.g. chown www-data)'
                : 'writable');
        }

        // Database + schema
        try {
            Database::connection()->query('SELECT 1');
            if (!Schema::isInstalled()) {
                $add('schema', 'Database schema', 'warn', 'not installed yet');
            } else {
                $stored = self::storedState();
                $pending = $stored !== self::expectedState();
                $storedVer = $stored !== '' ? (int) strtok($stored, '@') : 0;
                $add('schema', 'Database schema', $pending ? 'warn' : 'ok',
                    'schema v' . ($storedVer ?: '?') . ' / code v' . self::SCHEMA_VERSION
                    . ($pending ? ' - migrations pending (they run on the next request, or: php bin/mova-doctor.php --migrate)' : ' - up to date'));
                $err = self::lastMigrationError();
                if ($err) {
                    $add('migration_error', 'Last migration', 'error', 'FAILED ref ' . ($err['ref'] ?? '?') . ' at ' . ($err['time'] ?? '?') . ': ' . ($err['message'] ?? ''));
                }
                // Columns the 1.2.x editor reads.
                $cols = array_column(Database::fetchAll('PRAGMA table_info(content)'), 'name');
                $need = ['id', 'type', 'title', 'slug', 'body', 'status', 'created_at', 'updated_at'];
                $missing = array_values(array_diff($need, $cols));
                $add('content_columns', 'Content table', $missing ? 'error' : 'ok', $missing ? 'missing columns: ' . implode(', ', $missing) : count($cols) . ' columns');
                // Full-text indexes (a damaged FTS5 index breaks HQ search for 3+ characters only)
                if (class_exists(\Mova\Hq\SearchIndex::class)) {
                    $h = \Mova\Hq\SearchIndex::health();
                    $add('search_index', 'Search index', $h['ok'] ? 'ok' : 'error', $h['ok']
                        ? $h['detail'] . '; site search ' . $h['content_fts']
                        : $h['detail'] . '; site search ' . $h['content_fts'] . ' - fix: HQ search → Rebuild index, or php bin/mova-doctor.php --repair-search');
                }
                // Physical integrity (a raw copy over a live WAL database - e.g. 1.2.2 backup restore - shows up here)
                $qc = (string) Database::connection()->query('PRAGMA quick_check')->fetchColumn();
                $add('db_integrity', 'Database integrity', $qc === 'ok' ? 'ok' : 'error', $qc === 'ok'
                    ? 'quick_check ok'
                    : mb_substr_safe($qc, 160) . ' - restore a recent backup (HQ → Backups, or php bin/mova-doctor.php --restore=<file>)');
                $lr = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'last_restore'");
                if ($lr) {
                    $r = json_decode((string) $lr['setting_value'], true) ?: [];
                    $add('last_restore', 'Last restore', !empty($r['media_failed']) ? 'warn' : 'ok', ($r['file'] ?? '?') . ' at ' . ($r['at'] ?? '?')
                        . ' (made by ' . (($r['from_version'] ?? '') ?: '?') . ')' . (!empty($r['safety_backup']) ? '; undo: ' . $r['safety_backup'] : '')
                        . (!empty($r['media_failed']) ? '; ' . (int) $r['media_failed'] . ' media file(s) not written' : ''));
                }
            }
            $left = array_merge(glob(Bootstrap::path('storage') . '/restore_*') ?: [], glob(Bootstrap::path('storage') . '/backup_stage_*') ?: [],
                glob((string) Bootstrap::config('database.path') . '.restore-*') ?: []);
            $left = array_values(array_filter($left, static fn ($f) => @filemtime($f) < time() - 900));
            if ($left) {
                $add('restore_leftovers', 'Backup temp files', 'warn', count($left) . ' leftover temp item(s) in storage/ from an interrupted backup/restore (safe to delete): '
                    . implode(', ', array_map('basename', array_slice($left, 0, 3))));
            }
        } catch (\Throwable $e) {
            $add('database', 'Database', 'error', mb_substr_safe($e->getMessage(), 200));
        }

        // Code version + fingerprint
        $add('version', 'Mova version', 'ok', Bootstrap::version() . ' (code ' . self::fingerprint() . ')');

        // Recent errors
        $log = ErrorHandler::logDir() . '/mova-errors.log';
        if (is_file($log)) {
            // Informational lines (mova.upgrade = code change detected, mova.hq.search = index repaired, mova.backup.restore = restore done, mova.search.drop = unreadable index removed for rebuild) are not errors.
            $errs = array_values(array_filter(self::tail($log, 50), static fn ($l) => strpos($l, '] mova.upgrade ') === false && strpos($l, '] mova.hq.search ') === false && strpos($l, '] mova.backup.restore ') === false && strpos($l, '] mova.search.drop ') === false));
            $last = $errs === [] ? '' : (string) end($errs);
            $ts = preg_match('/^\[([0-9T:\-]+Z)\]/', $last, $m) ? (int) strtotime($m[1]) : 0;
            $age = $ts > 0 ? time() - $ts : PHP_INT_MAX;
            $add('errors', 'Recent errors', $age < 86400 ? 'warn' : 'ok', $last === '' ? 'none' : ('last ' . self::ago(min($age, 999999999)) . ' ago: ' . mb_substr_safe($last, 240)));
        } else {
            $add('errors', 'Recent errors', 'ok', 'none logged');
        }
        return $items;
    }

    public static function tail(string $file, int $n): array
    {
        $size = (int) @filesize($file);
        $fh = @fopen($file, 'rb');
        if (!$fh) {
            return [];
        }
        fseek($fh, max(0, $size - 16384));
        $data = (string) stream_get_contents($fh);
        fclose($fh);
        $lines = array_values(array_filter(explode("\n", $data), static fn ($l) => strpos($l, '[') === 0));
        return array_slice($lines, -$n);
    }

    private static function ago(int $s): string
    {
        if ($s < 120) {
            return $s . 's';
        }
        if ($s < 7200) {
            return intdiv($s, 60) . 'm';
        }
        return intdiv($s, 3600) . 'h';
    }

    private static function whoami(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $u = @posix_getpwuid(posix_geteuid());
            if (is_array($u) && isset($u['name'])) {
                return $u['name'];
            }
        }
        return 'the PHP user';
    }
}

if (!function_exists(__NAMESPACE__ . '\\mb_substr_safe')) {
    /** mb_substr when mbstring is present, byte substr otherwise. */
    function mb_substr_safe(string $s, int $len): string
    {
        return function_exists('mb_substr') ? mb_substr($s, 0, $len) : substr($s, 0, $len);
    }
}
