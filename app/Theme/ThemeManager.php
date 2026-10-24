<?php
/**
 * Mova — theme system (Phase 3)
 */

namespace Mova\Theme;

use Mova\Core\Bootstrap;
use Mova\Core\Database;
use Mova\Site\SiteService;

class ThemeManager
{
    public const FALLBACK = 'presence';

    /** Request-scoped preview theme (HQ theme gallery / signed mockup capture). Never persisted. */
    private static ?string $preview = null;

    public static function setPreview(?string $slug): void
    {
        $slug = $slug !== null ? preg_replace('/[^a-z0-9_\-]/', '', strtolower($slug)) : null;
        self::$preview = $slug ?: null;
    }

    public static function previewSlug(): ?string
    {
        return self::$preview;
    }

    /** HMAC for signed preview URLs (?mova_theme=slug&mova_exp=ts&mova_sig=...). */
    public static function previewSignature(string $payload): string
    {
        $secret = '';
        try {
            $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'preview_secret'");
            $secret = (string) ($row['setting_value'] ?? '');
            if ($secret === '') {
                $secret = bin2hex(random_bytes(24));
                Database::query(
                    "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('preview_secret', :v, :t)
                     ON CONFLICT(setting_key) DO NOTHING",
                    ['v' => $secret, 't' => date('c')]
                );
                $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'preview_secret'");
                $secret = (string) ($row['setting_value'] ?? $secret);
            }
        } catch (\Throwable $e) {
            $secret = (string) (Bootstrap::config('app_key', '') ?: __FILE__);
        }
        return hash_hmac('sha256', $payload, $secret);
    }

    public static function signedQuery(array $params, int $ttl = 3600): string
    {
        ksort($params);
        $params['mova_exp'] = (string) (time() + $ttl);
        $payload = http_build_query($params);
        return $payload . '&mova_sig=' . self::previewSignature($payload);
    }

    public static function verifySignedQuery(array $get): bool
    {
        $sig = (string) ($get['mova_sig'] ?? '');
        $exp = (int) ($get['mova_exp'] ?? 0);
        if ($sig === '' || $exp < time()) {
            return false;
        }
        $p = $get;
        unset($p['mova_sig'], $p['mova_exp'], $p['mx_static']);
        $keep = [];
        foreach ($p as $k => $v) {
            if (strpos((string) $k, 'mova_') === 0) {
                $keep[$k] = $v;
            }
        }
        ksort($keep);
        $keep['mova_exp'] = (string) $exp;
        return hash_equals(self::previewSignature(http_build_query($keep)), $sig);
    }
    public function themesPath(): string
    {
        return Bootstrap::path('themes') ?: (dirname(__DIR__, 2) . '/mova-themes');
    }

    public function all(): array
    {
        $dir = $this->themesPath();
        $themes = [];
        if (!is_dir($dir)) {
            return $themes;
        }
        foreach (scandir($dir) ?: [] as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }
            $base = $dir . '/' . $slug;
            if (!is_dir($base)) {
                continue;
            }
            $meta = [
                'slug' => $slug,
                'name' => ucfirst($slug),
                'description' => '',
                'version' => '1.0.0',
            ];
            $jsonFile = $base . '/theme.json';
            if (is_file($jsonFile)) {
                $json = json_decode((string) file_get_contents($jsonFile), true);
                if (is_array($json)) {
                    $meta = array_merge($meta, $json);
                    $meta['slug'] = $slug;
                }
            }
            $meta['has_layout'] = is_file($base . '/layout.php');
            $themes[] = $meta;
        }
        return $themes;
    }

    public function activeSlug(): string
    {
        if (self::$preview !== null && is_dir($this->themesPath() . '/' . self::$preview)) {
            return self::$preview;
        }
        $site = SiteService::current();
        if ($site && !empty($site['theme'])) {
            return $site['theme'];
        }
        try {
            $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'active_theme'");
            if ($row && $row['setting_value']) {
                return $row['setting_value'];
            }
        } catch (\Throwable $e) {
        }
        return is_dir($this->themesPath() . '/' . self::FALLBACK) ? self::FALLBACK : 'default';
    }

    public function path(?string $slug = null): string
    {
        $slug = $slug ?: $this->activeSlug();
        $path = $this->themesPath() . '/' . $slug;
        if (!is_dir($path) || !is_file($path . '/layout.php')) {
            $path = $this->themesPath() . '/' . self::FALLBACK;
            if (!is_dir($path)) {
                $path = $this->themesPath() . '/default';
            }
        }
        return $path;
    }

    public function setActive(string $slug): bool
    {
        $path = $this->themesPath() . '/' . $slug;
        if (!is_dir($path)) {
            return false;
        }
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('active_theme', :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            ['v' => $slug, 't' => date('c'), 'v2' => $slug, 't2' => date('c')]
        );

        // Update primary site theme
        $site = (new SiteService())->primary();
        if ($site) {
            Database::update('sites', ['theme' => $slug, 'updated_at' => date('c')], 'id = :id', ['id' => $site['id']]);
        }
        return true;
    }

    public function render(string $view, array $data = []): string
    {
        $file = $this->path() . '/' . $view . '.php';
        if (!is_file($file)) {
            $file = $this->themesPath() . '/default/' . $view . '.php';
        }
        extract($data);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
