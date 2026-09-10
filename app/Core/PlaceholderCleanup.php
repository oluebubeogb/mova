<?php
declare(strict_types=1);

namespace Mova\Core;

/**
 * Host control panels often drop index.html (or similar) into public_html.
 * Apache DirectoryIndex may serve that before index.php, so visitors see
 * the host placeholder instead of Mova. We detect and safely rename them.
 */
class PlaceholderCleanup
{
    /** Filenames commonly left by hosts / builders (basename only). */
    private const CANDIDATES = [
        'index.html',
        'index.htm',
        'default.html',
        'default.htm',
        'placeholder.html',
        'comingsoon.html',
        'coming-soon.html',
        'under-construction.html',
        'underconstruction.html',
        'home.html',
    ];

    public static function publicRoot(): string
    {
        $root = Bootstrap::path('public') ?: Bootstrap::path('root');
        if (!$root) {
            $root = dirname(__DIR__, 2);
        }
        return rtrim($root, '/\\');
    }

    /**
     * @return list<array{name: string, path: string, size: int}>
     */
    public static function findConflicts(): array
    {
        $root = self::publicRoot();
        $found = [];
        foreach (self::CANDIDATES as $name) {
            $path = $root . '/' . $name;
            if (is_file($path)) {
                $found[] = [
                    'name' => $name,
                    'path' => $path,
                    'size' => (int) filesize($path),
                ];
            }
        }
        return $found;
    }

    /**
     * Rename conflicts so they are no longer DirectoryIndex candidates.
     * Files move to storage/host-placeholders/ with a timestamp suffix.
     *
     * @return list<array{from: string, to: string}>
     */
    public static function neutralize(): array
    {
        $root = self::publicRoot();
        $destDir = Bootstrap::path('storage') . '/host-placeholders';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        if (!is_dir($destDir) || !is_writable($destDir)) {
            // Fallback: rename in place with .mova-bak suffix
            return self::renameInPlace($root);
        }

        $moved = [];
        $stamp = date('Ymd-His');
        foreach (self::findConflicts() as $item) {
            $target = $destDir . '/' . $item['name'] . '.' . $stamp . '.bak';
            if (@rename($item['path'], $target)) {
                $moved[] = ['from' => $item['name'], 'to' => $target];
            } elseif (@rename($item['path'], $item['path'] . '.mova-bak')) {
                $moved[] = ['from' => $item['name'], 'to' => $item['name'] . '.mova-bak'];
            }
        }
        return $moved;
    }

    /**
     * @return list<array{from: string, to: string}>
     */
    private static function renameInPlace(string $root): array
    {
        $moved = [];
        foreach (self::findConflicts() as $item) {
            $to = $item['path'] . '.mova-bak';
            if (@rename($item['path'], $to)) {
                $moved[] = ['from' => $item['name'], 'to' => basename($to)];
            }
        }
        return $moved;
    }
}
