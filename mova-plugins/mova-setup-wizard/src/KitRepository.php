<?php
/**
 * Resolve site kits from mova-kits/ (external catalog) or plugin bundled fallback.
 */

declare(strict_types=1);

namespace MovaSetupWizard;

final class KitRepository
{
    public static function kitsRoot(): string
    {
        // Standard: sibling of mova-plugins
        $candidates = [];
        $pluginRoot = dirname(__DIR__); // mova-setup-wizard
        $pluginsDir = dirname($pluginRoot); // mova-plugins
        $movaRoot = dirname($pluginsDir);
        $candidates[] = $movaRoot . '/mova-kits';
        if (class_exists(\Mova\Core\Bootstrap::class)) {
            try {
                $p = \Mova\Core\Bootstrap::path('kits');
                if (is_string($p) && $p !== '') {
                    array_unshift($candidates, $p);
                }
            } catch (\Throwable $e) {
            }
        }
        // Bundled fallback inside plugin
        $candidates[] = $pluginRoot . '/kits';
        foreach ($candidates as $dir) {
            if (is_dir($dir) && (is_file($dir . '/manifest.json') || self::hasAnyKitJson($dir))) {
                return $dir;
            }
        }
        return $movaRoot . '/mova-kits';
    }

    private static function hasAnyKitJson(string $dir): bool
    {
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (is_file($dir . '/' . $name . '/kit.json')) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    public static function list(?string $pack = null): array
    {
        $root = self::kitsRoot();
        $manifestFile = $root . '/manifest.json';
        $items = [];
        if (is_file($manifestFile)) {
            $data = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($data) && !empty($data['kits']) && is_array($data['kits'])) {
                $items = $data['kits'];
            }
        }
        if ($items === []) {
            foreach (scandir($root) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $kitFile = $root . '/' . $name . '/kit.json';
                if (!is_file($kitFile)) {
                    continue;
                }
                $kit = json_decode((string) file_get_contents($kitFile), true);
                if (!is_array($kit)) {
                    continue;
                }
                $items[] = [
                    'id' => $kit['id'] ?? $name,
                    'pack' => $kit['pack'] ?? 'generic',
                    'label' => $kit['label'] ?? $name,
                    'description' => $kit['description'] ?? '',
                    'version' => $kit['version'] ?? '1.0.0',
                    'preview' => ($kit['preview'] ?? null) ? ($name . '/' . $kit['preview']) : null,
                    'path' => $name,
                ];
            }
        }
        if ($pack !== null && $pack !== '') {
            // Map wizard pack ids to kit pack field
            $map = ['school' => 'school', 'organization' => 'organization', 'generic' => 'generic'];
            $want = $map[$pack] ?? $pack;
            $items = array_values(array_filter($items, static function ($i) use ($want) {
                return ($i['pack'] ?? '') === $want;
            }));
        }
        return $items;
    }

    /** @return array<string,mixed>|null Full kit.json + resolved files */
    public static function load(string $id): ?array
    {
        $root = self::kitsRoot();
        $path = null;
        foreach (self::list() as $item) {
            if (($item['id'] ?? '') === $id) {
                $path = $root . '/' . ($item['path'] ?? $id);
                break;
            }
        }
        if ($path === null) {
            $path = $root . '/' . $id;
        }
        $kitFile = $path . '/kit.json';
        if (!is_file($kitFile)) {
            return null;
        }
        $kit = json_decode((string) file_get_contents($kitFile), true);
        if (!is_array($kit)) {
            return null;
        }
        $kit['_path'] = $path;
        $kit['pages_resolved'] = [];
        foreach ($kit['pages'] ?? [] as $page) {
            $file = $path . '/' . ($page['file'] ?? '');
            $html = is_file($file) ? (string) file_get_contents($file) : '';
            $kit['pages_resolved'][] = array_merge($page, ['html' => $html]);
        }
        $css = '';
        foreach ($kit['css'] ?? [] as $rel) {
            $f = $path . '/' . $rel;
            if (is_file($f)) {
                $css .= (string) file_get_contents($f) . "\n";
            }
        }
        $js = '';
        foreach ($kit['js'] ?? [] as $rel) {
            $f = $path . '/' . $rel;
            if (is_file($f)) {
                $js .= (string) file_get_contents($f) . "\n";
            }
        }
        $kit['css_combined'] = $css;
        $kit['js_combined'] = $js;
        return $kit;
    }
}
