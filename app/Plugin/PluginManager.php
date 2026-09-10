<?php
/**
 * Mova — plugin / extension system (Phase 3)
 * Plugins live in /mova-plugins/{slug}/ with plugin.php bootstrap.
 */

namespace Mova\Plugin;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

class PluginManager
{
    /** @var array<string, list<callable>> */
    private static array $hooks = [];

    public function pluginsPath(): string
    {
        return \Mova\Core\Bootstrap::path('plugins') ?: (dirname(__DIR__, 2) . '/mova-plugins');
    }

    public function discover(): array
    {
        $dir = $this->pluginsPath();
        $found = [];
        if (!is_dir($dir)) {
            return $found;
        }
        foreach (scandir($dir) ?: [] as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }
            $base = $dir . '/' . $slug;
            $metaFile = $base . '/plugin.json';
            $boot = $base . '/plugin.php';
            if (!is_file($boot)) {
                continue;
            }
            $meta = [
                'slug' => $slug,
                'name' => $slug,
                'version' => '0.1.0',
                'description' => '',
            ];
            if (is_file($metaFile)) {
                $json = json_decode((string) file_get_contents($metaFile), true);
                if (is_array($json)) {
                    $meta = array_merge($meta, $json);
                    $meta['slug'] = $slug;
                }
            }
            $row = Database::fetch("SELECT * FROM plugins WHERE slug = :s", ['s' => $slug]);
            $meta['status'] = $row['status'] ?? 'inactive';
            $meta['db_id'] = $row['id'] ?? null;
            $found[] = $meta;
        }
        return $found;
    }

    public function activate(string $slug): bool
    {
        $boot = $this->pluginsPath() . '/' . $slug . '/plugin.php';
        if (!is_file($boot)) {
            return false;
        }
        $metaFile = $this->pluginsPath() . '/' . $slug . '/plugin.json';
        $name = $slug;
        $version = '0.1.0';
        if (is_file($metaFile)) {
            $json = json_decode((string) file_get_contents($metaFile), true) ?: [];
            $name = $json['name'] ?? $name;
            $version = $json['version'] ?? $version;
        }

        $existing = Database::fetch("SELECT id FROM plugins WHERE slug = :s", ['s' => $slug]);
        $now = date('c');
        if ($existing) {
            Database::update('plugins', [
                'status' => 'active',
                'name' => $name,
                'version' => $version,
                'activated_at' => $now,
            ], 'id = :id', ['id' => $existing['id']]);
        } else {
            Database::insert('plugins', [
                'slug' => $slug,
                'name' => $name,
                'version' => $version,
                'status' => 'active',
                'config' => '{}',
                'installed_at' => $now,
                'activated_at' => $now,
            ]);
        }
        return true;
    }

    public function deactivate(string $slug): bool
    {
        return Database::update('plugins', ['status' => 'inactive'], 'slug = :s', ['s' => $slug]) > 0;
    }

    public function bootActive(): void
    {
        $rows = [];
        try {
            $rows = Database::fetchAll("SELECT slug FROM plugins WHERE status = 'active'");
        } catch (\Throwable $e) {
            return;
        }
        foreach ($rows as $row) {
            $file = $this->pluginsPath() . '/' . $row['slug'] . '/plugin.php';
            if (is_file($file)) {
                try {
                    require_once $file;
                } catch (\Throwable $e) {
                    // isolate plugin failures
                }
            }
        }
    }

    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$hooks[$hook][$priority][] = $callback;
        ksort(self::$hooks[$hook]);
    }

    /**
     * Register a filter callback (same storage as actions; use applyFilters to run).
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::addAction($hook, $callback, $priority);
    }

    public static function doAction(string $hook, ...$args): void
    {
        if (empty(self::$hooks[$hook])) {
            return;
        }
        foreach (self::$hooks[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                try {
                    $cb(...$args);
                } catch (\Throwable $e) {
                    // isolate plugin failures
                }
            }
        }
    }

    /** @return mixed */
    public static function applyFilters(string $hook, $value, ...$args)
    {
        if (empty(self::$hooks[$hook])) {
            return $value;
        }
        foreach (self::$hooks[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                try {
                    $value = $cb($value, ...$args);
                } catch (\Throwable $e) {
                    // isolate plugin failures
                }
            }
        }
        return $value;
    }
}
