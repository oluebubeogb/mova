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
        return 'default';
    }

    public function path(?string $slug = null): string
    {
        $slug = $slug ?: $this->activeSlug();
        $path = $this->themesPath() . '/' . $slug;
        if (!is_dir($path)) {
            $path = $this->themesPath() . '/default';
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
