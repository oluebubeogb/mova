<?php
/**
 * Mova CMS - Backup & restore (.mova archives)
 */

namespace Mova\Backup;

use Mova\Core\Bootstrap;

class BackupService
{
    public function create(string $suffix = ''): string
    {
        $dir = Bootstrap::path('backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $suffix = preg_replace('/[^a-z0-9\-]/', '', strtolower($suffix));
        $name = 'mova-backup-' . date('Ymd-His') . ($suffix !== '' ? '-' . $suffix : '') . '.mova';
        $path = $dir . '/' . $name;

        $manifest = [
            'mova_version' => Bootstrap::version(),
            'created_at'   => date('c'),
            'files'        => [],
        ];

        $staging = Bootstrap::path('storage') . '/backup_stage_' . bin2hex(random_bytes(4));
        mkdir($staging, 0755, true);
        mkdir($staging . '/config', 0755, true);
        mkdir($staging . '/media', 0755, true);

        // Database
        $dbPath = Bootstrap::config('database.path');
        if (is_file($dbPath)) {
            \Mova\Core\Database::snapshotTo($staging . '/database.sqlite');
            $manifest['files'][] = 'database.sqlite';
        }

        $configPath = Bootstrap::path('root') . '/config/config.php';
        if (is_file($configPath)) {
            copy($configPath, $staging . '/config/config.php');
            $manifest['files'][] = 'config/config.php';
        }

        try {
            $rows = \Mova\Core\Database::fetchAll("SELECT setting_key, setting_value FROM settings");
            $settings = [];
            foreach ($rows as $r) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }
            file_put_contents(
                $staging . '/config/settings.json',
                json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $manifest['files'][] = 'config/settings.json';
        } catch (\Throwable $e) {
        }

        $uploads = Bootstrap::path('uploads');
        if (is_dir($uploads)) {
            $this->copyDir($uploads, $staging . '/media');
            $manifest['files'][] = 'media/';
        }

        file_put_contents($staging . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $this->archiveDir($staging, $path);
        $this->rmDir($staging);

        return $path;
    }


    public const MAX_PER_MONTH = 10;

    /**
     * Create backup then enforce monthly retention (max 10 in current calendar month).
     */
    public function createManaged(): string
    {
        $path = $this->create();
        $this->enforceMonthlyLimit(self::MAX_PER_MONTH);
        $this->touchLastBackupMeta();
        return $path;
    }

    /**
     * Ensure a backup exists for the current ISO week; create one if missing.
     * @return string|null path if created, null if already covered
     */
    public function ensureWeeklyBackup(): ?string
    {
        $year = (int) date('o'); // ISO year
        $week = (int) date('W');
        $backups = $this->listBackups();
        foreach ($backups as $b) {
            $y = (int) date('o', $b['mtime']);
            $w = (int) date('W', $b['mtime']);
            if ($y === $year && $w === $week) {
                return null; // already have one this week
            }
        }
        return $this->createManaged();
    }

    /**
     * Keep at most $max backups whose mtime falls in the given month (default: current).
     * Deletes oldest first.
     */
    public function enforceMonthlyLimit(int $max = self::MAX_PER_MONTH, ?int $year = null, ?int $month = null): int
    {
        $year = $year ?? (int) date('Y');
        $month = $month ?? (int) date('n');
        $inMonth = [];
        foreach ($this->listBackups() as $b) {
            if ((int) date('Y', $b['mtime']) === $year && (int) date('n', $b['mtime']) === $month) {
                $inMonth[] = $b;
            }
        }
        // Automatic pre-restore safety copies go first, then oldest first, so a few restores never
        // push out a backup the owner made on purpose.
        usort($inMonth, static function ($a, $b) {
            $pa = str_contains((string) $a['name'], '-pre-restore') ? 0 : 1;
            $pb = str_contains((string) $b['name'], '-pre-restore') ? 0 : 1;
            return [$pa, $a['mtime']] <=> [$pb, $b['mtime']];
        });
        $removed = 0;
        while (count($inMonth) > $max) {
            $old = array_shift($inMonth);
            if ($this->delete($old['name'])) {
                $removed++;
            }
        }
        return $removed;
    }

    public function monthlyStats(): array
    {
        $year = (int) date('Y');
        $month = (int) date('n');
        $count = 0;
        foreach ($this->listBackups() as $b) {
            if ((int) date('Y', $b['mtime']) === $year && (int) date('n', $b['mtime']) === $month) {
                $count++;
            }
        }
        $weekCovered = false;
        $y = (int) date('o');
        $w = (int) date('W');
        foreach ($this->listBackups() as $b) {
            if ((int) date('o', $b['mtime']) === $y && (int) date('W', $b['mtime']) === $w) {
                $weekCovered = true;
                break;
            }
        }
        return [
            'year' => $year,
            'month' => $month,
            'count' => $count,
            'max' => self::MAX_PER_MONTH,
            'week_covered' => $weekCovered,
            'iso_week' => $w,
        ];
    }

    private function touchLastBackupMeta(): void
    {
        try {
            \Mova\Core\Database::query(
                "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('last_backup_at', :v, :t)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
                ['v' => date('c'), 't' => date('c'), 'v2' => date('c'), 't2' => date('c')]
            );
        } catch (\Throwable $e) {
        }
    }

    public function listBackups(): array
    {
        $dir = Bootstrap::path('backups');
        if (!is_dir($dir)) {
            return [];
        }
        $files = array_merge(glob($dir . '/*.mova') ?: [], glob($dir . '/*.zip') ?: []);
        rsort($files);
        $out = [];
        foreach ($files as $f) {
            $out[] = [
                'name'  => basename($f),
                'path'  => $f,
                'size'  => filesize($f),
                'mtime' => filemtime($f),
            ];
        }
        return $out;
    }

    /** Tables a database must have to be accepted as a Mova backup. */
    private const REQUIRED_TABLES = ['settings', 'content', 'users'];

    /**
     * Restore a .mova backup. Safe by construction (1.2.3):
     *  1. extract only expected entries (no path traversal), validate manifest + database BEFORE touching the site;
     *  2. refuse backups made by a newer Mova than the running code (clear message, nothing changed);
     *  3. take an automatic "pre-restore" backup, then swap the database atomically (no copy over the open WAL file);
     *  4. run schema migrations immediately (older backups are upgraded in place) - on failure the previous
     *     database is put back, so the site is never left half-restored;
     *  5. copy media, then rebuild derived data (page cache, HQ search, site search, site AI index, asset versions).
     *
     * @return array{file:string, from_version:string, safety_backup:?string, media_failed:int, refreshed:string[], migrated:bool, ref:string}
     */
    public function restore(string $archivePath): array
    {
        if (!is_file($archivePath)) {
            throw new \InvalidArgumentException('Backup file not found.');
        }
        $storage = Bootstrap::path('storage');
        $lock = @fopen($storage . '/.mova-restore.lock', 'c');
        if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Another restore is already running. Try again in a minute.');
        }
        $tmp = $storage . '/restore_' . bin2hex(random_bytes(4));
        $rollbackDb = null;
        $dest = (string) Bootstrap::config('database.path');
        try {
            mkdir($tmp, 0755, true);
            $this->extractBackup($archivePath, $tmp);

            // ---- validate (nothing on the live site has been touched yet) ----
            $manifest = is_file($tmp . '/manifest.json') ? (json_decode((string) file_get_contents($tmp . '/manifest.json'), true) ?: []) : [];
            $fromVersion = (string) ($manifest['mova_version'] ?? '');
            $code = Bootstrap::version();
            if ($fromVersion !== '' && preg_match('/^\d+\.\d+/', $fromVersion) && version_compare(self::baseVersion($fromVersion), self::baseVersion($code), '>')) {
                throw new \RuntimeException('This backup was made by Mova ' . $fromVersion . ', newer than this site (' . $code . '). Upgrade Mova to ' . $fromVersion . ' or later first, then restore. Nothing was changed.');
            }
            $dbSrc = $tmp . '/database.sqlite';
            if (!is_file($dbSrc)) {
                throw new \RuntimeException('This archive has no database.sqlite - it is not a Mova site backup. Nothing was changed.');
            }
            $this->validateBackupDatabase($dbSrc);

            // ---- safety net: full pre-restore backup (visible in HQ, restorable) + a fast rollback copy ----
            $safety = null;
            try {
                $safety = basename($this->create('pre-restore'));
            } catch (\Throwable $e) {
                \Mova\Core\ErrorHandler::report('backup.restore.safety', $e);
            }
            $rollbackDb = $storage . '/restore_rollback_' . bin2hex(random_bytes(4)) . '.sqlite';
            \Mova\Core\Database::snapshotTo($rollbackDb);

            // ---- swap + migrate (rolled back on failure) ----
            $this->replaceDatabase($dbSrc, $dest, $tmp);
            $migrated = true;
            try {
                if (class_exists(\Mova\Core\Runtime::class)) {
                    $migrated = \Mova\Core\Runtime::migrateIfNeeded(true);
                }
                if (!$migrated) {
                    $err = \Mova\Core\Runtime::lastMigrationError();
                    throw new \RuntimeException('Upgrading the restored database to this Mova version failed'
                        . ($err ? ' (ref ' . ($err['ref'] ?? '?') . ': ' . ($err['message'] ?? '') . ')' : '') . '.');
                }
                foreach (self::REQUIRED_TABLES as $t) {
                    \Mova\Core\Database::fetch('SELECT 1 FROM ' . $t . ' LIMIT 1');
                }
            } catch (\Throwable $e) {
                \Mova\Core\ErrorHandler::report('backup.restore.migrate', $e);
                $this->replaceDatabase($rollbackDb, $dest, $tmp);
                try {
                    \Mova\Core\Runtime::migrateIfNeeded(true);
                } catch (\Throwable $e2) {
                }
                throw new \RuntimeException('Restore was rolled back - your site is unchanged. ' . $e->getMessage());
            }

            // ---- media (merge; failures are reported, not fatal) ----
            $mediaFailed = 0;
            if (is_dir($tmp . '/media')) {
                $mediaFailed = $this->copyDir($tmp . '/media', Bootstrap::path('uploads'));
            }
            // settings.json is a dump of the same database's settings table: only used when the archive has no DB.

            $refreshed = [];
            try {
                $refreshed = SitePackImporter::refreshCaches();
            } catch (\Throwable $e) {
                \Mova\Core\ErrorHandler::report('backup.restore.refresh', $e);
            }
            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }
            $ref = \Mova\Core\ErrorHandler::log('backup.restore', 'Restored ' . basename($archivePath)
                . ' (made by ' . ($fromVersion ?: 'unknown') . ', code ' . $code . ')'
                . ($safety ? '; pre-restore backup ' . $safety : '') . ($mediaFailed ? '; ' . $mediaFailed . ' media file(s) not written' : ''));
            try {
                \Mova\Core\Database::query(
                    "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('last_restore', :v, :t)
                     ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at",
                    ['v' => json_encode(['file' => basename($archivePath), 'at' => date('c'), 'from_version' => $fromVersion, 'safety_backup' => $safety, 'media_failed' => $mediaFailed, 'ref' => $ref]), 't' => date('c')]
                );
            } catch (\Throwable $e) {
            }
            return ['file' => basename($archivePath), 'from_version' => $fromVersion, 'safety_backup' => $safety,
                'media_failed' => $mediaFailed, 'refreshed' => $refreshed, 'migrated' => $migrated, 'ref' => (string) $ref];
        } finally {
            $this->rmDir($tmp);
            if ($rollbackDb !== null) {
                @unlink($rollbackDb);
            }
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private static function baseVersion(string $v): string
    {
        return preg_match('/^(\d+\.\d+(?:\.\d+)?)/', $v, $m) ? $m[1] : '0';
    }

    /** Integrity + "is this a Mova database" check of a backup DB. Throws with a user-facing message. */
    private function validateBackupDatabase(string $file): void
    {
        try {
            $chk = new \PDO('sqlite:' . $file, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $ok = (string) $chk->query('PRAGMA quick_check')->fetchColumn();
            $tables = $chk->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
            $chk = null;
        } catch (\Throwable $e) {
            throw new \RuntimeException('The backup database cannot be read (' . mb_substr($e->getMessage(), 0, 120) . '). Nothing was changed.');
        }
        if ($ok !== 'ok') {
            throw new \RuntimeException('The backup database is damaged (quick_check: ' . mb_substr($ok, 0, 120) . '). Nothing was changed.');
        }
        $missing = array_diff(self::REQUIRED_TABLES, $tables);
        if ($missing) {
            throw new \RuntimeException('The backup database is not a Mova site (missing tables: ' . implode(', ', $missing) . '). Nothing was changed.');
        }
    }

    /**
     * Extract only the entries a backup is supposed to contain, refusing absolute paths, "..", and anything
     * outside manifest.json / database.sqlite(-wal) / config/settings.json / media/**. (config.php is never restored.)
     */
    private function extractBackup(string $archive, string $dest): void
    {
        $allowed = static function (string $name): bool {
            $name = str_replace('\\', '/', $name);
            if ($name === '' || $name[0] === '/' || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('#^[A-Za-z]:#', $name) || str_contains($name, "\0")) {
                return false;
            }
            return in_array($name, ['manifest.json', 'database.sqlite', 'config/settings.json'], true)
                || (str_starts_with($name, 'media/') && !preg_match('#\.(php\d?|phtml|phar|htaccess)$#i', $name));
        };
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($archive) === true) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    if (str_ends_with($name, '/') || !$allowed($name)) {
                        continue;
                    }
                    $target = $dest . '/' . $name;
                    if (!is_dir(dirname($target))) {
                        mkdir(dirname($target), 0755, true);
                    }
                    $in = $zip->getStream($name);
                    $out = $in ? fopen($target, 'wb') : false;
                    if (!$in || !$out || stream_copy_to_stream($in, $out) === false) {
                        throw new \RuntimeException('Could not extract ' . $name . ' from the backup (disk full or storage/ not writable?). Nothing was changed.');
                    }
                    fclose($in);
                    fclose($out);
                }
                $zip->close();
                return;
            }
        }
        // tar / tar.gz fallback (servers without ZipArchive): PharData, then drop anything not allowed.
        $raw = $dest . '_raw';
        try {
            $tmp = $dest . '_archive.tar.gz';
            copy($archive, $tmp);
            (new \PharData($tmp))->extractTo($raw);
            @unlink($tmp);
        } catch (\Throwable $e) {
            @unlink($dest . '_archive.tar.gz');
            $this->rmDir($raw);
            throw new \RuntimeException('Could not open the backup archive: ' . $e->getMessage());
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raw, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), strlen($raw) + 1);
            if ($f->isFile() && !$f->isLink() && $allowed($rel)) {
                if (!is_dir(dirname($dest . '/' . $rel))) {
                    mkdir(dirname($dest . '/' . $rel), 0755, true);
                }
                copy($f->getPathname(), $dest . '/' . $rel);
            }
        }
        $this->rmDir($raw);
    }

    /**
     * Swap in a restored database safely. The old code copy()'d over the live file while it was open in WAL
     * mode, leaving the old -wal/-shm beside a different main file: SQLite can then replay stale pages onto
     * it, which typically shows up first as a damaged FTS5 index (HQ search fails for 3+ characters).
     */
    private function replaceDatabase(string $src, string $dest, string $tmpDir): void
    {
        $chk = new \PDO('sqlite:' . $src, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $ok = (string) $chk->query('PRAGMA quick_check')->fetchColumn();
        $chk->exec('PRAGMA journal_mode = DELETE'); // fold any -wal shipped inside the archive into the file
        $chk = null;
        if ($ok !== 'ok') {
            throw new \RuntimeException('The backup database is damaged (quick_check: ' . mb_substr($ok, 0, 120) . '). Nothing was restored.');
        }
        try {
            $live = \Mova\Core\Database::connection();
            $live->exec('PRAGMA busy_timeout = 10000'); // let in-flight requests of other PHP workers finish their writes
            $live->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (\Throwable $e) {
        }
        \Mova\Core\Database::disconnect();
        gc_collect_cycles();
        $stage = $dest . '.restore-' . bin2hex(random_bytes(4));
        if (!copy($src, $stage)) {
            throw new \RuntimeException('Could not stage the restored database (disk full, or the database folder is not writable).');
        }
        // Keep the live file's mode/owner: a restore run as root (docker compose exec without -u www-data) must not
        // leave a root-owned database that PHP-FPM can no longer write to.
        $st = @stat($dest);
        if ($st) {
            @chmod($stage, $st['mode'] & 0777);
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                @chown($stage, $st['uid']);
                @chgrp($stage, $st['gid']);
            }
        }
        if (!@rename($stage, $dest)) {
            @unlink($stage);
            throw new \RuntimeException('Could not replace the database file.');
        }
        foreach (['-wal', '-shm', '-journal'] as $sfx) {
            if (is_file($dest . $sfx)) {
                @unlink($dest . $sfx);
            }
        }
    }

    public function delete(string $name): bool
    {
        $name = basename($name);
        if (!preg_match('/\.(mova|zip)$/', $name)) {
            return false;
        }
        $path = Bootstrap::path('backups') . '/' . $name;
        return is_file($path) ? unlink($path) : false;
    }


    /**
     * Build a full Mova install ZIP (same shape as a fresh package).
     * Optionally include this site's data (database, config, uploads, settings).
     *
     * @return string absolute path to the .zip
     */
    public function exportInstallZip(bool $includeSiteData = false): string
    {
        $root = dirname(__DIR__, 2); // project root (public_html)
        $dir = Bootstrap::path('backups');
        if ($dir === '' || $dir === null) {
            $dir = Bootstrap::path('storage') . '/backups';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $suffix = $includeSiteData ? 'with-data' : 'core';
        $name = 'mova-install-' . $suffix . '-' . date('Ymd-His') . '.zip';
        $path = rtrim((string) $dir, '/\\') . DIRECTORY_SEPARATOR . $name;

        $staging = Bootstrap::path('storage') . '/export_stage_' . bin2hex(random_bytes(4));
        // Package root matches shared-hosting layout: contents of public_html
        $pkg = $staging . '/mova-cms/mova-shared-hosting';
        @mkdir($pkg, 0755, true);

        // Core directories always included
        $dirs = ['app', 'assets', 'hq', 'mova-plugins', 'mova-themes'];
        foreach ($dirs as $d) {
            $src = $root . DIRECTORY_SEPARATOR . $d;
            if (is_dir($src)) {
                $this->copyDirFiltered($src, $pkg . DIRECTORY_SEPARATOR . $d, $includeSiteData);
            }
        }

        // Core root files
        foreach (['index.php', 'HOSTING.txt', 'mova.json', 'mova.txt', '.mova', '.htaccess'] as $f) {
            $src = $root . DIRECTORY_SEPARATOR . $f;
            if (is_file($src)) {
                copy($src, $pkg . DIRECTORY_SEPARATOR . $f);
            }
        }

        // Empty / minimal uploads
        $uploadsDest = $pkg . DIRECTORY_SEPARATOR . 'mova-uploads';
        @mkdir($uploadsDest, 0755, true);
        $upHt = $root . DIRECTORY_SEPARATOR . 'mova-uploads' . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_file($upHt)) {
            copy($upHt, $uploadsDest . DIRECTORY_SEPARATOR . '.htaccess');
        }

        // Storage skeleton
        $storageDest = $pkg . DIRECTORY_SEPARATOR . 'storage';
        @mkdir($storageDest . DIRECTORY_SEPARATOR . 'cache', 0755, true);
        $stHt = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_file($stHt)) {
            copy($stHt, $storageDest . DIRECTORY_SEPARATOR . '.htaccess');
        }
        file_put_contents($storageDest . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '.gitkeep', '');

        // Config is ALWAYS included so a core package can boot and reach /hq/install.
        // With site data: copy live config (+ optional settings dump).
        // Without site data: write a clean default config.php (no site secrets / overrides).
        $cfgDir = $pkg . DIRECTORY_SEPARATOR . 'config';
        @mkdir($cfgDir, 0755, true);
        $configHt = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '.htaccess';
        if (is_file($configHt)) {
            copy($configHt, $cfgDir . DIRECTORY_SEPARATOR . '.htaccess');
        } else {
            file_put_contents($cfgDir . DIRECTORY_SEPARATOR . '.htaccess',
                "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n"
            );
        }

        if ($includeSiteData) {
            // Database
            $dbPath = Bootstrap::config('database.path');
            if (is_string($dbPath) && is_file($dbPath)) {
                // Consistent snapshot (a raw copy of a live WAL database can be torn / corrupt FTS indexes)
                $snapTmp = Bootstrap::path('storage') . DIRECTORY_SEPARATOR . 'export_db_' . bin2hex(random_bytes(4)) . '.sqlite';
                \Mova\Core\Database::snapshotTo($snapTmp);
                $dbDir = $pkg . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database';
                @mkdir($dbDir, 0755, true);
                $dbName = basename($dbPath);
                copy($snapTmp, $dbDir . DIRECTORY_SEPARATOR . $dbName);
                // Also classic location if config points elsewhere
                copy($snapTmp, $pkg . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $dbName);
                @unlink($snapTmp);
            }

            // Live site config
            $configSrc = $root . DIRECTORY_SEPARATOR . 'config';
            if (is_dir($configSrc)) {
                $this->copyDirFiltered($configSrc, $cfgDir, true);
            }

            // Uploads (site media)
            $uploadsSrc = Bootstrap::path('uploads');
            if (is_dir($uploadsSrc)) {
                $this->copyDirFiltered($uploadsSrc, $uploadsDest, true);
            }

            // Optional: settings dump for clarity
            try {
                $rows = \Mova\Core\Database::fetchAll('SELECT setting_key, setting_value FROM settings');
                $settings = [];
                foreach ($rows as $r) {
                    $settings[$r['setting_key']] = $r['setting_value'];
                }
                file_put_contents(
                    $cfgDir . DIRECTORY_SEPARATOR . 'settings.export.json',
                    json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            } catch (\Throwable $e) {
            }
        } else {
            // Clean default config for fresh installs (flat / shared-hosting layout)
            $defaultConfig = <<<'MOVA_CFG'
<?php
/**
 * Mova CMS - Configuration
 * Content that moves.
 *
 * Supports two layouts:
 * 1) Standard: document root → /public  (app, config, storage sit above public)
 * 2) Shared hosting / flat: document root → project root (index.php next to /app)
 *    Used when a subdomain's public_html is the docroot (cPanel, DirectAdmin, etc.)
 */

$movaRoot = dirname(__DIR__);
// Flat mode: index.php lives beside /app (typical public_html upload)
$movaFlat = is_file($movaRoot . '/index.php') && is_dir($movaRoot . '/app');
$movaPublic = $movaFlat ? $movaRoot : ($movaRoot . '/public');

return [
    // Application
    'app_name'       => 'Mova',
    'app_tagline'    => 'Content that moves.',
    'app_version'    => '1.0.0',
    'app_url'        => '', // Auto-detected if empty
    'timezone'       => 'UTC',
    'debug'          => false,
    'install_mode'   => $movaFlat ? 'flat' : 'standard',

    // Paths (relative to root)
    'paths' => [
        'root'     => $movaRoot,
        'public'   => $movaPublic,
        'storage'  => $movaRoot . '/storage',
        'themes'   => $movaRoot . '/mova-themes',
        'plugins'  => $movaRoot . '/mova-plugins',
        'uploads'  => $movaPublic . '/mova-uploads',
        'cache'    => $movaRoot . '/storage/cache',
        'logs'     => $movaRoot . '/storage/logs',
        'backups'  => $movaRoot . '/storage/backups',
    ],

    // Database
    'database' => [
        'driver' => 'sqlite',
        'path'   => $movaRoot . '/storage/database.sqlite',
    ],

    // Security
    'security' => [
        'csrf_token_name'   => '_mova_csrf',
        'session_name'      => 'mova_session',
        'session_lifetime'  => 7200, // 2 hours
        'password_algo'     => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
        'login_max_attempts'=> 5,
        'login_lockout'     => 900, // 15 minutes
        'cookie_secure'     => true,
        'cookie_httponly'   => true,
        'cookie_samesite'   => 'Lax',
    ],

    // Content
    'content' => [
        'types' => [
            'article'       => 'Article',
            'page'          => 'Page',
            'guide'         => 'Guide',
            'documentation' => 'Documentation',
            'faq'           => 'FAQ',
            'custom'        => 'Custom',
        ],
        'statuses' => [
            'draft'     => 'Draft',
            'review'    => 'In review',
            'approved'  => 'Approved',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'archived'  => 'Archived',
            'trash'     => 'Trash',
        ],
        'default_type'   => 'article',
        'default_status' => 'draft',
        'per_page'       => 20,
    ],

    // Media
    'media' => [
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'],
        'max_upload_size'    => 10 * 1024 * 1024, // 10 MB
        'variants'           => [480, 768, 1200],
        'quality'            => 85,
        'convert_to_webp'    => true,
    ],

    // SEO defaults
    'seo' => [
        'title_separator' => ' — ',
        'default_robots'  => 'index, follow',
    ],

    // Cache
    'cache' => [
        'enabled' => true,
        'ttl'     => 3600, // 1 hour
    ],

    // Mail (SMTP)
    'mail' => [
        'driver'     => 'smtp',
        'host'       => '',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls',
        'from_email' => '',
        'from_name'  => 'Mova',
    ],

    // Reserved routes (cannot be used as content slugs)
    'reserved_routes' => [
        'hq', 'api', 'assets', 'mova-uploads', 'mova-plugins', 'mova-themes', 'search',
        'sitemap.xml', 'robots.txt', 'feed.xml', 'llms.txt',
    ],

    // Roles
    'roles' => [
        'owner'         => 'Owner',
        'administrator' => 'Administrator',
        'editor'        => 'Editor',
        'author'        => 'Author',
        'viewer'        => 'Viewer',
    ],
];
MOVA_CFG;
            file_put_contents($cfgDir . DIRECTORY_SEPARATOR . 'config.php', $defaultConfig);
        }

        // Manifest for the export
        $manifest = [
            'type' => 'mova_install_zip',
            'mova_version' => Bootstrap::version(),
            'created_at' => date('c'),
            'include_site_data' => $includeSiteData,
            'install' => 'Extract mova-cms/mova-shared-hosting/* into public_html (or open HOSTING.txt).',
        ];
        file_put_contents($pkg . DIRECTORY_SEPARATOR . 'EXPORT.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $readme = "Mova install package\n"
            . "====================\n\n"
            . ($includeSiteData
                ? "This ZIP includes Mova core PLUS this site's data (database, config, media).\n"
                : "This ZIP is a clean Mova core package (no site content).\n"
                  . "It includes a default config/ so a fresh install works on a new host.\n")
            . "\nInstall:\n"
            . "1. Extract so you see index.php, app/, hq/, assets/, config/ at the web root.\n"
            . "   (Copy everything inside mova-cms/mova-shared-hosting/ into public_html.)\n"
            . "2. Make storage/ and mova-uploads/ writable.\n"
            . "3. Open /hq/install (clean) or /hq/login (with data).\n";
        file_put_contents($staging . DIRECTORY_SEPARATOR . 'mova-cms' . DIRECTORY_SEPARATOR . 'README.txt', $readme);

        $this->archiveDir($staging, $path);
        $this->rmDir($staging);

        return $path;
    }

    /**
     * Copy a directory tree, skipping caches, backup stages, and nested export zips.
     */
    private function copyDirFiltered(string $src, string $dest, bool $includeSiteFiles): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        $skipNames = [
            'backup_stage_', 'export_stage_', 'restore_',
            '.git', 'node_modules', '.DS_Store',
        ];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $sub = $iterator->getSubPathName();
            $base = $item->getFilename();
            // Skip volatile / recursive paths
            if (str_contains($sub, 'storage' . DIRECTORY_SEPARATOR . 'cache')) {
                continue;
            }
            if (str_contains($sub, 'storage' . DIRECTORY_SEPARATOR . 'backups')) {
                continue;
            }
            foreach ($skipNames as $skip) {
                if (str_starts_with($base, $skip) || str_contains($sub, $skip)) {
                    continue 2;
                }
            }
            if (preg_match('/\.(mova|zip)$/i', $base) && str_contains($sub, 'backup')) {
                continue;
            }
            $target = $dest . DIRECTORY_SEPARATOR . $sub;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $parent = dirname($target);
                if (!is_dir($parent)) {
                    mkdir($parent, 0755, true);
                }
                @copy($item->getPathname(), $target);
            }
        }
    }

    private function archiveDir(string $source, string $dest): void
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create backup archive.');
            }
            $this->zipAddDir($zip, $source, '');
            $zip->close();
            return;
        }

        // Fallback: tar.gz renamed to .mova (still restorable)
        $tarPath = preg_replace('/\.mova$/', '.tar', $dest);
        try {
            $phar = new \PharData($tarPath);
            $phar->buildFromDirectory($source);
            if (function_exists('gzopen')) {
                $phar->compress(\Phar::GZ);
                @unlink($tarPath);
                $gz = $tarPath . '.gz';
                if (is_file($gz)) {
                    rename($gz, $dest);
                    return;
                }
            }
            rename($tarPath, $dest);
            return;
        } catch (\Throwable $e) {
            // Last resort: copy staging folder as a marked directory (not ideal)
            throw new \RuntimeException(
                'Backup requires the PHP zip or phar extension. ' . $e->getMessage()
            );
        }
    }

    private function extractArchive(string $archive, string $dest): void
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($archive) === true) {
                $zip->extractTo($dest);
                $zip->close();
                return;
            }
        }

        // Try as tar / tar.gz via PharData
        try {
            // PharData needs correct extension sometimes — copy to temp .tar.gz
            $tmp = $dest . '_archive.tar.gz';
            copy($archive, $tmp);
            $phar = new \PharData($tmp);
            $phar->extractTo($dest);
            @unlink($tmp);
            return;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not extract backup: ' . $e->getMessage());
        }
    }

    private function zipAddDir(\ZipArchive $zip, string $dir, string $prefix): void
    {
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            $local = ltrim($prefix . '/' . $item, '/');
            if (is_dir($path)) {
                $zip->addEmptyDir($local);
                $this->zipAddDir($zip, $path, $local);
            } else {
                $zip->addFile($path, $local);
            }
        }
    }

    /** @return int number of files that could not be written */
    private function copyDir(string $src, string $dest): int
    {
        $failed = 0;
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $dest . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                $parent = dirname($target);
                if (!is_dir($parent)) {
                    mkdir($parent, 0755, true);
                }
                if (!@copy($item->getPathname(), $target)) {
                    $failed++;
                }
            }
        }
        if ($failed) {
            \Mova\Core\ErrorHandler::log('backup.copy', $failed . ' file(s) could not be written under ' . basename($dest) . ' (check permissions / disk space)');
        }
        return $failed;
    }

    private function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
