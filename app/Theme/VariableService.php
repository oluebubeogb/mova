<?php
/**
 * Mova design / site variables — system tokens + custom vars for consistent styling.
 *
 * Usage in CSS:  var(--max-width)  or  var(--mova-my-gap)
 * Usage in HTML / assemblies:  {{var:site_name}}  {{var:max-width}}
 * Usage in JS (frontend):  window.MovaVars['site_name']
 */

namespace Mova\Theme;

use Mova\Core\Database;

class VariableService
{
    public const STORAGE_KEY = 'design_variables';

    /**
     * System variables derived from Brand / Style / Layout settings.
     * source = settings key or design path used when syncing from Var sheet.
     *
     * @return array<int, array{name:string,value:string,type:string,description:string,source:string,readonly?:bool}>
     */
    public static function systemCatalog(): array
    {
        $siteName = (string) DesignConfig::setting('site_name', 'Mova');
        $siteDesc = (string) DesignConfig::setting('site_description', '');
        $logo = (string) DesignConfig::setting('logo_url', '');
        $logoDark = (string) DesignConfig::setting('logo_url_dark', '');
        $favicon = (string) DesignConfig::setting('favicon_url', '');
        $brand = (string) DesignConfig::setting('brand_color', '#2563eb');

        $layout = DesignConfig::layout();
        $tokens = DesignConfig::tokens();
        $colors = $tokens['colors'] ?? [];
        $dark = $tokens['colors_dark'] ?? [];
        $radius = $tokens['radius'] ?? [];
        $spacing = $tokens['spacing'] ?? [];
        $typo = $tokens['typography'] ?? [];
        $shadows = $tokens['shadows'] ?? [];
        $components = DesignConfig::components();

        $container = (string) ($layout['container_width'] ?? '720px');
        $btnRadius = $radius[$components['button']['radius'] ?? 'md'] ?? ($radius['md'] ?? '10px');
        $cardRadius = $radius[$components['card']['radius'] ?? 'md'] ?? ($radius['md'] ?? '10px');

        $rows = [
            ['name' => 'site_name', 'value' => $siteName, 'type' => 'text', 'description' => 'Site name (Brand)', 'source' => 'setting:site_name'],
            ['name' => 'site_description', 'value' => $siteDesc, 'type' => 'text', 'description' => 'Tagline (Brand)', 'source' => 'setting:site_description'],
            ['name' => 'logo_url', 'value' => $logo, 'type' => 'url', 'description' => 'Logo (light)', 'source' => 'setting:logo_url'],
            ['name' => 'logo_url_dark', 'value' => $logoDark, 'type' => 'url', 'description' => 'Logo (dark mode)', 'source' => 'setting:logo_url_dark'],
            ['name' => 'favicon_url', 'value' => $favicon, 'type' => 'url', 'description' => 'Site icon / favicon', 'source' => 'setting:favicon_url'],
            ['name' => 'brand_color', 'value' => $brand, 'type' => 'color', 'description' => 'Brand accent color', 'source' => 'setting:brand_color'],

            ['name' => 'max-width', 'value' => $container, 'type' => 'length', 'description' => 'Content max width (Layout)', 'source' => 'layout:container_width'],
            // Built-in light palette (Style → Light colors)
            ['name' => 'color-primary', 'value' => (string) ($colors['primary'] ?? '#2563eb'), 'type' => 'color', 'description' => 'Primary (light)', 'source' => 'token:colors.primary'],
            ['name' => 'color-secondary', 'value' => (string) ($colors['secondary'] ?? '#64748b'), 'type' => 'color', 'description' => 'Secondary (light)', 'source' => 'token:colors.secondary'],
            ['name' => 'color-accent', 'value' => (string) ($colors['accent'] ?? '#7c3aed'), 'type' => 'color', 'description' => 'Accent (light)', 'source' => 'token:colors.accent'],
            ['name' => 'color-bg', 'value' => (string) ($colors['background'] ?? '#f8f9fb'), 'type' => 'color', 'description' => 'Background (light)', 'source' => 'token:colors.background'],
            ['name' => 'color-surface', 'value' => (string) ($colors['surface'] ?? '#ffffff'), 'type' => 'color', 'description' => 'Surface (light)', 'source' => 'token:colors.surface'],
            ['name' => 'color-text', 'value' => (string) ($colors['text'] ?? '#111827'), 'type' => 'color', 'description' => 'Text (light)', 'source' => 'token:colors.text'],
            ['name' => 'color-muted', 'value' => (string) ($colors['muted'] ?? '#6b7280'), 'type' => 'color', 'description' => 'Muted text (light)', 'source' => 'token:colors.muted'],
            ['name' => 'color-border', 'value' => (string) ($colors['border'] ?? '#e5e7eb'), 'type' => 'color', 'description' => 'Border (light)', 'source' => 'token:colors.border'],

            // Built-in dark palette (Style → Dark colors) — full set
            ['name' => 'color-primary-dark', 'value' => (string) ($dark['primary'] ?? '#60a5fa'), 'type' => 'color', 'description' => 'Primary (dark)', 'source' => 'token:colors_dark.primary'],
            ['name' => 'color-secondary-dark', 'value' => (string) ($dark['secondary'] ?? '#94a3b8'), 'type' => 'color', 'description' => 'Secondary (dark)', 'source' => 'token:colors_dark.secondary'],
            ['name' => 'color-accent-dark', 'value' => (string) ($dark['accent'] ?? '#a78bfa'), 'type' => 'color', 'description' => 'Accent (dark)', 'source' => 'token:colors_dark.accent'],
            ['name' => 'color-bg-dark', 'value' => (string) ($dark['background'] ?? '#0b0d12'), 'type' => 'color', 'description' => 'Background (dark)', 'source' => 'token:colors_dark.background'],
            ['name' => 'color-surface-dark', 'value' => (string) ($dark['surface'] ?? '#12151c'), 'type' => 'color', 'description' => 'Surface (dark)', 'source' => 'token:colors_dark.surface'],
            ['name' => 'color-text-dark', 'value' => (string) ($dark['text'] ?? '#f3f4f6'), 'type' => 'color', 'description' => 'Text (dark)', 'source' => 'token:colors_dark.text'],
            ['name' => 'color-muted-dark', 'value' => (string) ($dark['muted'] ?? '#9ca3af'), 'type' => 'color', 'description' => 'Muted text (dark)', 'source' => 'token:colors_dark.muted'],
            ['name' => 'color-border-dark', 'value' => (string) ($dark['border'] ?? '#1f2430'), 'type' => 'color', 'description' => 'Border (dark)', 'source' => 'token:colors_dark.border'],

            ['name' => 'radius-sm', 'value' => (string) ($radius['sm'] ?? '6px'), 'type' => 'length', 'description' => 'Radius small', 'source' => 'token:radius.sm'],
            ['name' => 'radius-md', 'value' => (string) ($radius['md'] ?? '10px'), 'type' => 'length', 'description' => 'Radius medium', 'source' => 'token:radius.md'],
            ['name' => 'radius-lg', 'value' => (string) ($radius['lg'] ?? '16px'), 'type' => 'length', 'description' => 'Radius large', 'source' => 'token:radius.lg'],
            ['name' => 'radius-button', 'value' => (string) $btnRadius, 'type' => 'length', 'description' => 'Button radius', 'source' => 'derived:radius-button', 'readonly' => true],
            ['name' => 'radius-card', 'value' => (string) $cardRadius, 'type' => 'length', 'description' => 'Card radius', 'source' => 'derived:radius-card', 'readonly' => true],

            ['name' => 'space-section', 'value' => (string) ($spacing['section'] ?? '2.5rem'), 'type' => 'length', 'description' => 'Section spacing', 'source' => 'token:spacing.section'],
            ['name' => 'space-element', 'value' => (string) ($spacing['element'] ?? '1.5rem'), 'type' => 'length', 'description' => 'Element spacing', 'source' => 'token:spacing.element'],
            ['name' => 'font-sans', 'value' => (string) ($typo['font_sans'] ?? 'system-ui, sans-serif'), 'type' => 'text', 'description' => 'Sans font stack', 'source' => 'token:typography.font_sans'],
            ['name' => 'shadow-sm', 'value' => (string) ($shadows['sm'] ?? '0 1px 2px rgba(0,0,0,0.04)'), 'type' => 'text', 'description' => 'Shadow small', 'source' => 'token:shadows.sm'],
            ['name' => 'shadow-md', 'value' => (string) ($shadows['md'] ?? '0 4px 12px rgba(0,0,0,0.08)'), 'type' => 'text', 'description' => 'Shadow medium', 'source' => 'token:shadows.md'],
        ];

        // Any extra light/dark palette keys not already listed (future-proof)
        $knownLight = ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'border'];
        $knownDark = $knownLight;
        $lightNameMap = [
            'primary' => 'color-primary', 'secondary' => 'color-secondary', 'accent' => 'color-accent',
            'background' => 'color-bg', 'surface' => 'color-surface', 'text' => 'color-text',
            'muted' => 'color-muted', 'border' => 'color-border',
        ];
        $darkNameMap = [
            'primary' => 'color-primary-dark', 'secondary' => 'color-secondary-dark', 'accent' => 'color-accent-dark',
            'background' => 'color-bg-dark', 'surface' => 'color-surface-dark', 'text' => 'color-text-dark',
            'muted' => 'color-muted-dark', 'border' => 'color-border-dark',
        ];
        $existingNames = [];
        foreach ($rows as $r) {
            $existingNames[$r['name']] = true;
        }
        if (is_array($colors)) {
            foreach ($colors as $key => $val) {
                $key = (string) $key;
                if ($key === '' || !is_string($val) && !is_numeric($val)) {
                    continue;
                }
                $name = $lightNameMap[$key] ?? ('color-' . preg_replace('/[^a-z0-9\-]/', '', strtolower($key)));
                if ($name === 'color-' || isset($existingNames[$name])) {
                    continue;
                }
                $rows[] = [
                    'name' => $name,
                    'value' => (string) $val,
                    'type' => 'color',
                    'description' => ucfirst($key) . ' (light palette)',
                    'source' => 'token:colors.' . $key,
                ];
                $existingNames[$name] = true;
            }
        }
        if (is_array($dark)) {
            foreach ($dark as $key => $val) {
                $key = (string) $key;
                if ($key === '' || (!is_string($val) && !is_numeric($val))) {
                    continue;
                }
                $name = $darkNameMap[$key] ?? ('color-' . preg_replace('/[^a-z0-9\-]/', '', strtolower($key)) . '-dark');
                if ($name === 'color--dark' || isset($existingNames[$name])) {
                    continue;
                }
                $rows[] = [
                    'name' => $name,
                    'value' => (string) $val,
                    'type' => 'color',
                    'description' => ucfirst($key) . ' (dark palette)',
                    'source' => 'token:colors_dark.' . $key,
                ];
                $existingNames[$name] = true;
            }
        }

        // Style → custom color swatches (light + dark), always reflected as variables
        $customColors = $tokens['custom_colors'] ?? [];
        if (is_array($customColors)) {
            foreach ($customColors as $cc) {
                if (!is_array($cc)) {
                    continue;
                }
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($cc['slug'] ?? ''))) ?? '';
                if ($slug === '') {
                    $label = (string) ($cc['label'] ?? '');
                    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($label)) ?? '';
                    $slug = trim($slug, '-');
                }
                if ($slug === '') {
                    continue;
                }
                $label = (string) ($cc['label'] ?? $slug);
                $lightVal = (string) ($cc['light'] ?? '#ffffff');
                $darkVal = (string) ($cc['dark'] ?? '#ffffff');

                $lightName = 'color-' . $slug;
                if (!isset($existingNames[$lightName])) {
                    $rows[] = [
                        'name' => $lightName,
                        'value' => $lightVal,
                        'type' => 'color',
                        'description' => $label . ' (custom, light)',
                        'source' => 'token:custom_colors.' . $slug . '.light',
                    ];
                    $existingNames[$lightName] = true;
                }
                $darkName = 'color-' . $slug . '-dark';
                if (!isset($existingNames[$darkName])) {
                    $rows[] = [
                        'name' => $darkName,
                        'value' => $darkVal,
                        'type' => 'color',
                        'description' => $label . ' (custom, dark)',
                        'source' => 'token:custom_colors.' . $slug . '.dark',
                    ];
                    $existingNames[$darkName] = true;
                }
            }
        }

        return $rows;
    }

    /** @return array<int, array{name:string,value:string,type:string,description:string,source:string}> */
    public static function customCatalog(): array
    {
        $raw = DesignConfig::setting(self::STORAGE_KEY, '[]');
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = self::normalizeName((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'value' => (string) ($row['value'] ?? ''),
                'type' => (string) ($row['type'] ?? 'text'),
                'description' => (string) ($row['description'] ?? ''),
                'source' => 'custom',
            ];
        }
        return $out;
    }

    /** @return array<int, array{name:string,value:string,type:string,description:string,source:string,readonly?:bool}> */
    public static function all(): array
    {
        $sys = self::systemCatalog();
        $custom = self::customCatalog();
        $names = [];
        foreach ($sys as $r) {
            $names[$r['name']] = true;
        }
        foreach ($custom as $r) {
            if (isset($names[$r['name']])) {
                continue; // system wins
            }
            $sys[] = $r;
        }
        return $sys;
    }

    /** @return array<string, string> name => value */
    public static function map(): array
    {
        $map = [];
        foreach (self::all() as $row) {
            $map[$row['name']] = $row['value'];
        }
        return $map;
    }

    public static function get(string $name, string $default = ''): string
    {
        $name = self::normalizeName($name);
        $map = self::map();
        return $map[$name] ?? $default;
    }

    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/^--/', '', $name) ?? $name;
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name) ?? '';
        return $name;
    }

    /**
     * Save custom variables only (does not overwrite system catalog entries).
     * @param array<int, array{name?:string,value?:string,type?:string,description?:string}> $rows
     */
    public static function saveCustom(array $rows): void
    {
        $clean = [];
        $seen = [];
        foreach ($rows as $row) {
            $name = self::normalizeName((string) ($row['name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            // Skip names that collide with system vars — those are updated via syncSystem
            foreach (self::systemCatalog() as $sys) {
                if ($sys['name'] === $name) {
                    continue 2;
                }
            }
            $seen[$name] = true;
            $clean[] = [
                'name' => $name,
                'value' => (string) ($row['value'] ?? ''),
                'type' => (string) ($row['type'] ?? 'text'),
                'description' => (string) ($row['description'] ?? ''),
            ];
        }
        DesignConfig::saveSetting(self::STORAGE_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Apply edits from Var sheet: update system sources + replace custom list.
     * @param array<int, array{name?:string,value?:string,type?:string,description?:string,source?:string}> $rows
     */
    public static function saveSheet(array $rows): void
    {
        $systemByName = [];
        foreach (self::systemCatalog() as $s) {
            $systemByName[$s['name']] = $s;
        }

        $custom = [];
        $seen = [];
        foreach ($rows as $row) {
            $name = self::normalizeName((string) ($row['name'] ?? ''));
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $value = (string) ($row['value'] ?? '');
            $type = (string) ($row['type'] ?? 'text');
            $desc = (string) ($row['description'] ?? '');

            if (isset($systemByName[$name])) {
                $src = $systemByName[$name]['source'] ?? '';
                if (!empty($systemByName[$name]['readonly'])) {
                    continue;
                }
                self::writeToSource($src, $value);
            } else {
                $custom[] = [
                    'name' => $name,
                    'value' => $value,
                    'type' => $type,
                    'description' => $desc,
                ];
            }
        }
        self::saveCustom($custom);
    }

    public static function writeToSource(string $source, string $value): void
    {
        if (strpos($source, 'setting:') === 0) {
            $key = substr($source, 8);
            DesignConfig::saveSetting($key, $value);
            return;
        }
        if (strpos($source, 'layout:') === 0) {
            $key = substr($source, 7);
            $layout = DesignConfig::layout();
            if ($key === 'container_width') {
                $layout['container_width'] = $value;
                DesignConfig::saveLayout($layout);
            }
            return;
        }
        if (strpos($source, 'token:') === 0) {
            $path = substr($source, 6);
            $tokens = DesignConfig::tokens();

            // Custom color swatches: custom_colors.{slug}.light|dark
            if (strpos($path, 'custom_colors.') === 0) {
                $rest = substr($path, strlen('custom_colors.'));
                $bits = explode('.', $rest);
                if (count($bits) >= 2) {
                    $slug = $bits[0];
                    $mode = $bits[1]; // light | dark
                    if (!isset($tokens['custom_colors']) || !is_array($tokens['custom_colors'])) {
                        $tokens['custom_colors'] = [];
                    }
                    $found = false;
                    foreach ($tokens['custom_colors'] as &$cc) {
                        if (!is_array($cc)) {
                            continue;
                        }
                        $s = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($cc['slug'] ?? ''))) ?? '';
                        if ($s === $slug) {
                            if ($mode === 'light' || $mode === 'dark') {
                                $cc[$mode] = $value;
                            }
                            $found = true;
                            break;
                        }
                    }
                    unset($cc);
                    if (!$found) {
                        $tokens['custom_colors'][] = [
                            'slug' => $slug,
                            'label' => $slug,
                            'light' => $mode === 'light' ? $value : '#ffffff',
                            'dark' => $mode === 'dark' ? $value : '#ffffff',
                        ];
                    }
                    DesignConfig::saveTokens($tokens);
                }
                return;
            }

            $parts = explode('.', $path, 2);
            if (count($parts) !== 2) {
                return;
            }
            [$group, $key] = $parts;
            if (!isset($tokens[$group]) || !is_array($tokens[$group])) {
                $tokens[$group] = [];
            }
            $tokens[$group][$key] = $value;
            DesignConfig::saveTokens($tokens);
            // Keep brand_color in sync with primary when edited
            if ($group === 'colors' && $key === 'primary') {
                DesignConfig::saveSetting('brand_color', $value);
            }
        }
    }

    /** Extra CSS custom properties for user-defined variables. */
    public static function customCssVariables(): string
    {
        $lines = [':root {'];
        foreach (self::customCatalog() as $row) {
            $cssName = self::toCssName($row['name']);
            $val = self::cssSafe($row['value']);
            $lines[] = '  ' . $cssName . ': ' . $val . ';';
        }
        $lines[] = '}';
        // Aliases for common system names without --color- prefix confusion
        $lines[] = ':root {';
        $lines[] = '  --mova-site-name: "' . self::cssSafe(self::get('site_name', 'Mova')) . '";';
        $lines[] = '}';
        return implode("\n", $lines);
    }

    public static function toCssName(string $name): string
    {
        $name = self::normalizeName($name);
        if (strpos($name, 'color-') === 0 || strpos($name, 'radius-') === 0
            || strpos($name, 'space-') === 0 || strpos($name, 'font-') === 0
            || strpos($name, 'shadow-') === 0 || $name === 'max-width'
            || strpos($name, 'mova-') === 0) {
            return '--' . $name;
        }
        return '--mova-' . $name;
    }

    public static function cssSafe(string $v): string
    {
        $v = trim($v);
        $v = str_replace([';', '{', '}', '<', '>'], '', $v);
        return $v !== '' ? $v : 'inherit';
    }

    /**
     * Replace {{var:name}} and {{site_name}} style tokens in HTML/assembly strings.
     */
    public static function applyTemplate(string $html): string
    {
        $map = self::map();
        $html = preg_replace_callback('/\{\{\s*var:([a-zA-Z0-9_\-]+)\s*\}\}/', static function ($m) use ($map) {
            $key = $m[1];
            return htmlspecialchars($map[$key] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $html) ?? $html;

        // Common short forms already used in assemblies
        $replacements = [
            '{{site_name}}' => $map['site_name'] ?? 'Mova',
            '{{site_description}}' => $map['site_description'] ?? '',
            '{{year}}' => date('Y'),
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /** JSON for window.MovaVars */
    public static function jsPayload(): string
    {
        return json_encode(self::map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Parse a var-sheet text block (name = value per line, # comments).
     * @return array<int, array{name:string,value:string}>
     */
    public static function parseSheetText(string $text): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $name = self::normalizeName($name);
            if ($name === '') {
                continue;
            }
            $rows[] = ['name' => $name, 'value' => $value];
        }
        return $rows;
    }

    public static function sheetText(): string
    {
        $lines = [
            '# Mova variable sheet',
            '# name = value',
            '# System vars (from Brand / Style / Layout) sync back when saved.',
            '# Add new lines for custom vars. Delete a custom line to remove it.',
            '',
        ];
        foreach (self::all() as $row) {
            $src = $row['source'] ?? '';
            $tag = $src === 'custom' ? 'custom' : 'system:' . $src;
            if (!empty($row['readonly'])) {
                $tag .= ' (readonly)';
            }
            $lines[] = '# [' . $tag . '] ' . ($row['description'] ?? '');
            $lines[] = $row['name'] . ' = ' . $row['value'];
            $lines[] = '';
        }
        return implode("\n", $lines);
    }
}
