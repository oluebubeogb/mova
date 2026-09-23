<?php
/**
 * Mova package / site manifest (dynamic /mova.json).
 *
 * Static product fields come from public/mova.defaults.json (or legacy mova.json).
 * Site identity and dates are filled from HQ settings + install signals.
 */

declare(strict_types=1);

namespace Mova\Core;

use Mova\Theme\DesignConfig;

class MovaManifest
{
    /**
     * Build the public manifest array (product + live site fields).
     *
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $meta = self::loadDefaults();

        // Version: prefer config app_version so releases stay in sync without editing JSON
        $ver = (string) Bootstrap::config('app_version', $meta['version'] ?? '1.0.0');
        if ($ver !== '') {
            $meta['version'] = $ver;
        }

        // Tagline from product defaults; optional override from HQ description
        $hqDesc = trim((string) DesignConfig::setting('site_description', ''));
        if ($hqDesc !== '' && empty($meta['tagline'])) {
            $meta['tagline'] = $hqDesc;
        }

        // Paths from config when available
        try {
            $paths = Bootstrap::config('paths', []);
            if (is_array($paths)) {
                $meta['paths'] = array_merge(
                    is_array($meta['paths'] ?? null) ? $meta['paths'] : [],
                    [
                        'uploads' => 'mova-uploads',
                        'plugins' => 'mova-plugins',
                        'themes'  => 'mova-themes',
                    ]
                );
            }
        } catch (\Throwable $e) {
            // keep defaults
        }

        if (!isset($meta['site']) || !is_array($meta['site'])) {
            $meta['site'] = [];
        }

        // Site name from HQ Brand (DesignConfig) — this is the live name
        $siteName = trim((string) DesignConfig::setting('site_name', ''));
        if ($siteName === '') {
            $siteName = (string) Bootstrap::config('app_name', $meta['site']['name'] ?? 'Mova');
        }
        $meta['site']['name'] = $siteName;

        // Dates from DB
        [$created, $updated] = self::resolveSiteDates();
        if ($created !== '') {
            $meta['site']['created_at'] = $created;
        }
        if ($updated !== '') {
            $meta['site']['updated_at'] = $updated;
        }

        // Optional: expose resolved base URL for tools
        try {
            $base = Bootstrap::baseUrl();
            if ($base !== '' && empty($meta['site']['url'])) {
                $meta['site']['url'] = rtrim($base, '/');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $meta;
    }

    /**
     * JSON response body (pretty-printed).
     */
    public static function toJson(): string
    {
        $json = json_encode(
            self::build(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        return $json !== false ? $json : '{}';
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadDefaults(): array
    {
        $candidates = [];
        try {
            $public = Bootstrap::path('public');
            $candidates[] = $public . '/mova.defaults.json';
            // Legacy static file (if not deleted yet) — used only as defaults source
            $candidates[] = $public . '/mova.json';
        } catch (\Throwable $e) {
            // fall through
        }
        $candidates[] = dirname(__DIR__, 2) . '/public/mova.defaults.json';

        foreach ($candidates as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }
            $raw = @file_get_contents($file);
            if ($raw === false || $raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'name'    => 'mova',
            'title'   => 'Mova CMS',
            'tagline' => 'Content that moves.',
            'version' => '1.1.3',
            'php'     => '>=8.1',
            'channel' => 'stable',
            'site'    => ['name' => 'Mova', 'created_at' => '', 'updated_at' => ''],
            'paths'   => [
                'uploads' => 'mova-uploads',
                'plugins' => 'mova-plugins',
                'themes'  => 'mova-themes',
            ],
        ];
    }

    /**
     * created_at: installed_at setting, else earliest owner/user, else site_name row time.
     * updated_at: site_name setting updated_at, else max settings updated_at.
     *
     * @return array{0: string, 1: string}
     */
    private static function resolveSiteDates(): array
    {
        $created = '';
        $updated = '';

        try {
            $installed = DesignConfig::setting('installed_at', '');
            if (is_string($installed) && $installed !== '') {
                $created = $installed;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            if ($created === '') {
                $row = Database::fetch(
                    "SELECT created_at FROM users WHERE role = 'owner' ORDER BY id ASC LIMIT 1"
                );
                if ($row && !empty($row['created_at'])) {
                    $created = (string) $row['created_at'];
                }
            }
            if ($created === '') {
                $row = Database::fetch(
                    "SELECT created_at FROM users ORDER BY id ASC LIMIT 1"
                );
                if ($row && !empty($row['created_at'])) {
                    $created = (string) $row['created_at'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $row = Database::fetch(
                "SELECT updated_at FROM settings WHERE setting_key = 'site_name' LIMIT 1"
            );
            if ($row && !empty($row['updated_at'])) {
                $updated = (string) $row['updated_at'];
                if ($created === '') {
                    $created = $updated;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            if ($updated === '') {
                $row = Database::fetch(
                    "SELECT MAX(updated_at) AS u FROM settings"
                );
                if ($row && !empty($row['u'])) {
                    $updated = (string) $row['u'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Normalize to date-only when value is ISO datetime (stable for JSON consumers)
        $created = self::normalizeDate($created);
        $updated = self::normalizeDate($updated);

        return [$created, $updated];
    }

    private static function normalizeDate(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        // Keep full ISO if already compact date
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v, $m)) {
            return substr($v, 0, 10);
        }
        $ts = strtotime($v);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        return $v;
    }
}
