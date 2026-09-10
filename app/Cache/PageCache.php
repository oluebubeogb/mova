<?php
/**
 * Mova CMS - Simple page cache (anonymous HTML)
 */

namespace Mova\Cache;

use Mova\Core\Bootstrap;

class PageCache
{
    public static function get(string $key): ?string
    {
        if (!Bootstrap::config('cache.enabled', true)) {
            return null;
        }

        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }

        $ttl = (int) Bootstrap::config('cache.ttl', 3600);
        if ($ttl > 0 && (time() - filemtime($file) > $ttl)) {
            @unlink($file);
            return null;
        }

        return file_get_contents($file) ?: null;
    }

    public static function put(string $key, string $html): void
    {
        if (!Bootstrap::config('cache.enabled', true)) {
            return;
        }

        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        file_put_contents(self::path($key), $html, LOCK_EX);
    }

    public static function forget(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function flush(): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.html') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** Purge a content slug and common listing pages (home, feeds). */
    public static function invalidateContent(string $slug): void
    {
        $slug = trim($slug, '/');
        self::forget('page_' . md5('/' . $slug));
        self::forget('page_' . md5('/'));
        self::forget('page_' . md5(''));
        self::forget('sitemap');
        self::forget('feed');
        self::forget('llms');
        // Full flush is safest on shared hosting when cache keys may include query strings
        if (Bootstrap::config('cache.flush_on_publish', true)) {
            self::flush();
        }
    }

    private static function dir(): string
    {
        $dir = Bootstrap::path('cache');
        if ($dir === '') {
            $dir = Bootstrap::path('storage') . DIRECTORY_SEPARATOR . 'cache';
        }
        return $dir;
    }

    private static function path(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $key) ?: 'page';
        return self::dir() . DIRECTORY_SEPARATOR . $safe . '.html';
    }
}
