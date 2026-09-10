<?php
/**
 * Mova CMS - Backup & restore (.mova archives)
 */

namespace Mova\Backup;

use Mova\Core\Bootstrap;

class BackupService
{
    public function create(): string
    {
        $dir = Bootstrap::path('backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'mova-backup-' . date('Ymd-His') . '.mova';
        $path = $dir . '/' . $name;

        $manifest = [
            'mova_version' => Bootstrap::config('app_version', '1.0.0'),
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
            try {
                $pdo = \Mova\Core\Database::connection();
                $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (\Throwable $e) {
            }
            copy($dbPath, $staging . '/database.sqlite');
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
        // oldest first
        usort($inMonth, static fn($a, $b) => $a['mtime'] <=> $b['mtime']);
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

    public function restore(string $archivePath): void
    {
        if (!is_file($archivePath)) {
            throw new \InvalidArgumentException('Backup file not found.');
        }

        $tmp = Bootstrap::path('storage') . '/restore_' . bin2hex(random_bytes(4));
        mkdir($tmp, 0755, true);
        $this->extractArchive($archivePath, $tmp);

        $dbSrc = $tmp . '/database.sqlite';
        if (is_file($dbSrc)) {
            $dbDest = Bootstrap::config('database.path');
            copy($dbSrc, $dbDest);
        }

        $mediaSrc = $tmp . '/media';
        if (is_dir($mediaSrc)) {
            $this->copyDir($mediaSrc, Bootstrap::path('uploads'));
        }

        $settingsFile = $tmp . '/config/settings.json';
        if (is_file($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
            foreach ($settings as $k => $v) {
                \Mova\Core\Database::query(
                    "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
                     ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
                    ['k' => $k, 'v' => $v, 't' => date('c'), 'v2' => $v, 't2' => date('c')]
                );
            }
        }

        $this->rmDir($tmp);
        \Mova\Cache\PageCache::flush();
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
                try {
                    $pdo = \Mova\Core\Database::connection();
                    $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
                } catch (\Throwable $e) {
                }
                $dbDir = $pkg . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'database';
                @mkdir($dbDir, 0755, true);
                $dbName = basename($dbPath);
                copy($dbPath, $dbDir . DIRECTORY_SEPARATOR . $dbName);
                // Also classic location if config points elsewhere
                copy($dbPath, $pkg . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . $dbName);
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
        'password_algo'     => PASSWORD_ARGON2ID,
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
            'mova_version' => Bootstrap::config('app_version', '1.0.0'),
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

    private function copyDir(string $src, string $dest): void
    {
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
                copy($item->getPathname(), $target);
            }
        }
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
