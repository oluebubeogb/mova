<?php
declare(strict_types=1);

namespace Mova\Core;

/**
 * Environment checks for install / health.
 * PHP extensions are reported but do not hard-block install — users can proceed
 * with warnings. Writable storage is strongly recommended for SQLite.
 */
class Requirements
{
    /**
     * @return list<array{id:string,label:string,required:bool,ok:bool,detail:string,group:string}>
     */
    public static function check(): array
    {
        $items = [];

        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        $items[] = [
            'id'       => 'php',
            'label'    => 'PHP 8.1+',
            'required' => true,
            'ok'       => $phpOk,
            'detail'   => 'Running ' . PHP_VERSION,
            'group'    => 'php',
        ];

        $items[] = self::ext('pdo', 'PDO', true, '', 'php');
        $items[] = self::ext('pdo_sqlite', 'PDO SQLite (pdo_sqlite)', true, 'Needed for the default database', 'php');
        $items[] = self::ext('json', 'JSON', true, '', 'php');
        $items[] = self::ext('mbstring', 'Multibyte String (mbstring)', true, '', 'php');
        $items[] = self::ext('session', 'Sessions', true, '', 'php');
        $items[] = self::ext('openssl', 'OpenSSL', true, '', 'php');
        $items[] = self::ext('filter', 'Filter', true, '', 'php');

        $items[] = self::ext('gd', 'GD (images / WebP)', false, 'Recommended for media uploads', 'php');
        $items[] = self::ext('fileinfo', 'Fileinfo (MIME types)', false, 'Recommended for uploads', 'php');
        $items[] = self::ext('zip', 'Zip (ZipArchive)', false, 'Recommended for .mova backups', 'php');
        $items[] = self::ext('curl', 'cURL', false, 'Optional outbound HTTP / update checks', 'php');
        $items[] = self::ext('imap', 'IMAP', false, 'Optional — HQ Mailbox only', 'php');
        $items[] = self::ext('zlib', 'Zlib', false, 'Optional backup compression', 'php');

        $writable = [
            'storage' => Bootstrap::path('storage'),
            'uploads' => Bootstrap::path('uploads'),
            'backups' => Bootstrap::path('backups'),
        ];
        foreach ($writable as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $ok = is_dir($path) && is_writable($path);
            $items[] = [
                'id'       => 'dir_' . $label,
                'label'    => 'Writable: ' . $label . '/',
                'required' => $label === 'storage',
                'ok'       => $ok,
                'detail'   => $path . ($ok ? '' : ' — not writable'),
                'group'    => 'disk',
            ];
        }

        $conflicts = PlaceholderCleanup::findConflicts();
        $items[] = [
            'id'       => 'no_host_placeholder',
            'label'    => 'No host index.html blocking the site',
            'required' => false,
            'ok'       => $conflicts === [],
            'detail'   => $conflicts === []
                ? 'OK'
                : 'Found: ' . implode(', ', array_column($conflicts, 'name')) . ' — auto-renamed on install',
            'group'    => 'host',
        ];

        return $items;
    }

    /**
     * Hard blockers only (e.g. storage not writable). PHP gaps never block.
     */
    public static function allRequiredOk(): bool
    {
        foreach (self::check() as $item) {
            if (($item['group'] ?? '') === 'disk' && $item['required'] && !$item['ok']) {
                return false;
            }
        }
        return true;
    }

    /** True when every PHP "required" extension/version item is OK. */
    public static function phpRequiredOk(): bool
    {
        foreach (self::check() as $item) {
            if (($item['group'] ?? '') === 'php' && $item['required'] && !$item['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return array{
     *   required_ok:bool,
     *   php_ok:bool,
     *   disk_ok:bool,
     *   missing_required:list<string>,
     *   missing_optional:list<string>,
     *   missing_php:list<string>,
     *   items:list
     * }
     */
    public static function summary(): array
    {
        $items = self::check();
        $missingRequired = [];
        $missingOptional = [];
        $missingPhp = [];
        $phpOk = true;
        $diskOk = true;

        foreach ($items as $item) {
            if ($item['ok']) {
                continue;
            }
            if (($item['group'] ?? '') === 'php') {
                $missingPhp[] = $item['label'];
                if ($item['required']) {
                    $phpOk = false;
                }
            }
            if (($item['group'] ?? '') === 'disk' && $item['required']) {
                $diskOk = false;
                $missingRequired[] = $item['label'];
            } elseif ($item['required']) {
                $missingRequired[] = $item['label'];
            } else {
                $missingOptional[] = $item['label'];
            }
        }

        return [
            'required_ok'      => $diskOk, // install may proceed without full PHP set
            'php_ok'           => $phpOk,
            'disk_ok'          => $diskOk,
            'missing_required' => $missingRequired,
            'missing_optional' => $missingOptional,
            'missing_php'      => $missingPhp,
            'items'            => $items,
        ];
    }

    private static function ext(string $name, string $label, bool $required, string $hint = '', string $group = 'php'): array
    {
        $ok = extension_loaded($name);
        $detail = $ok ? 'Loaded' : ('Missing' . ($hint !== '' ? ' — ' . $hint : ''));
        return [
            'id'       => 'ext_' . $name,
            'label'    => $label,
            'required' => $required,
            'ok'       => $ok,
            'detail'   => $detail,
            'group'    => $group,
        ];
    }
}
