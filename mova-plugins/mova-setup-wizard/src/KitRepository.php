<?php
/**
 * Resolve site kits from mova-kits/ or plugin-bundled kits/.
 */

declare(strict_types=1);

namespace MovaSetupWizard;

final class KitRepository
{
    public static function kitsRoot(): string
    {
        foreach (self::candidateRoots() as $dir) {
            if (self::isKitsDir($dir)) {
                return $dir;
            }
        }
        return dirname(__DIR__) . '/kits';
    }

    /** @return list<string> */
    public static function candidateRoots(): array
    {
        $pluginRoot = dirname(__DIR__);
        $pluginsDir = dirname($pluginRoot);
        $movaRoot = dirname($pluginsDir);

        $candidates = [
            $movaRoot . '/mova-kits',
            $pluginRoot . '/kits',
            $movaRoot . '/public/mova-kits',
            $pluginsDir . '/../mova-kits',
        ];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $doc = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\');
            $candidates[] = $doc . '/mova-kits';
            $candidates[] = dirname($doc) . '/mova-kits';
            $candidates[] = dirname($doc) . '/mova-plugins/mova-setup-wizard/kits';
        }

        if (class_exists(\Mova\Core\Bootstrap::class)) {
            try {
                $app = '';
                if (method_exists(\Mova\Core\Bootstrap::class, 'path')) {
                    $app = (string) \Mova\Core\Bootstrap::path('app');
                }
                if ($app !== '') {
                    $base = dirname($app);
                    $candidates[] = $base . '/mova-kits';
                    $candidates[] = $base . '/mova-plugins/mova-setup-wizard/kits';
                }
                $k = \Mova\Core\Bootstrap::path('kits');
                if (is_string($k) && $k !== '') {
                    array_unshift($candidates, $k);
                }
            } catch (\Throwable $e) {
            }
        }

        $out = [];
        $seen = [];
        foreach ($candidates as $c) {
            $c = str_replace('\\', '/', $c);
            if ($c === '' || isset($seen[$c])) {
                continue;
            }
            $seen[$c] = true;
            $out[] = $c;
        }
        return $out;
    }

    private static function isKitsDir(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        if (is_file($dir . '/manifest.json')) {
            return true;
        }
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
        $items = [];
        $manifestFile = $root . '/manifest.json';
        if (is_file($manifestFile)) {
            $data = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($data) && !empty($data['kits']) && is_array($data['kits'])) {
                $items = $data['kits'];
            }
        }
        if ($items === []) {
            foreach (scandir($root) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name === 'README.md') {
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
                    'id' => (string) ($kit['id'] ?? $name),
                    'pack' => (string) ($kit['pack'] ?? 'generic'),
                    'label' => (string) ($kit['label'] ?? $name),
                    'description' => (string) ($kit['description'] ?? ''),
                    'version' => (string) ($kit['version'] ?? '1.0.0'),
                    'preview' => isset($kit['preview']) ? ($name . '/' . $kit['preview']) : null,
                    'path' => $name,
                ];
            }
        }

        if ($pack !== null && $pack !== '') {
            $want = self::normalizePack($pack);
            $items = array_values(array_filter($items, static function ($i) use ($want) {
                return self::normalizePack((string) ($i['pack'] ?? '')) === $want;
            }));
        }
        return $items;
    }

    public static function normalizePack(string $pack): string
    {
        $pack = strtolower(trim($pack));
        return match ($pack) {
            'org', 'organisation', 'organization' => 'organization',
            'school', 'schools' => 'school',
            'basic', 'simple', 'generic', 'other' => 'generic',
            default => $pack,
        };
    }

    /** @return array<string,mixed>|null */
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
