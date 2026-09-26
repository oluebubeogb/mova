<?php
/**
 * Mova — design tokens, layout, and component variant configuration
 */

namespace Mova\Theme;

use Mova\Core\Database;

class DesignConfig
{
    public const TOKEN_KEY = 'design_tokens';
    public const LAYOUT_KEY = 'layout_config';
    public const COMPONENTS_KEY = 'component_variants';
    public const MOTION_KEY = 'motion_config';

    /** Default design tokens (light base; dark overrides derived where possible) */
    public static function defaultTokens(): array
    {
        return [
            'colors' => [
                'primary' => '#2563eb',
                'secondary' => '#64748b',
                'accent' => '#7c3aed',
                'background' => '#f8f9fb',
                'surface' => '#ffffff',
                'text' => '#111827',
                'muted' => '#6b7280',
                'border' => '#e5e7eb',
            ],
            'colors_dark' => [
                'primary' => '#60a5fa',
                'secondary' => '#94a3b8',
                'accent' => '#a78bfa',
                'background' => '#0b0d12',
                'surface' => '#12151c',
                'text' => '#f3f4f6',
                'muted' => '#9ca3af',
                'border' => '#1f2430',
            ],
            'typography' => [
                'font_sans' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif",
                'font_mono' => "'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace",
                'scale' => '1', // 0.9 compact, 1 normal, 1.1 large
            ],
            'spacing' => [
                'section' => '2.5rem',
                'element' => '1.5rem',
                'density' => 'normal', // compact | normal | spacious
            ],
            'radius' => [
                'sm' => '6px',
                'md' => '10px',
                'lg' => '16px',
                'full' => '999px',
            ],
            'shadows' => [
                'style' => 'soft', // none | soft | medium | sharp
                'sm' => '0 1px 2px rgba(0, 0, 0, 0.04)',
                'md' => '0 4px 12px rgba(0, 0, 0, 0.08)',
            ],
        ];
    }

    public static function defaultLayout(): array
    {
        return [
            'container_width' => '720px',
            'header' => [
                'type' => 'sticky', // sticky | static | floating
                'logo_position' => 'left', // left | center
                'nav_align' => 'right', // left | center | right
                'transparent' => false,
            ],
            'footer' => [
                'style' => 'simple', // simple | centered | columns
            ],
            'mobile_nav' => [
                // drawer-top | drawer-right | drawer-left | fullscreen | bottom-sheet
                'style' => 'drawer-right',
                // left | center | right — text/link alignment inside the panel
                'content_align' => 'left',
            ],
        ];
    }

    public static function defaultComponents(): array
    {
        return [
            'button' => [
                'variant' => 'solid', // solid | outline | ghost | soft
                'radius' => 'md', // sm | md | lg | full
                'uppercase' => false,
            ],
            'card' => [
                'variant' => 'elevated', // flat | elevated | outlined | glass
                'radius' => 'md',
                'hover' => 'lift', // none | lift | scale | border
            ],
            'hero' => [
                'variant' => 'centered', // centered | split | minimal | fullscreen
            ],
        ];
    }

    public static function get(string $key, array $defaults): array
    {
        try {
            $row = Database::fetch(
                "SELECT setting_value FROM settings WHERE setting_key = :k",
                ['k' => $key]
            );
            if ($row && $row['setting_value'] !== null && $row['setting_value'] !== '') {
                $decoded = json_decode((string) $row['setting_value'], true);
                if (is_array($decoded)) {
                    return self::mergeDeep($defaults, $decoded);
                }
            }
        } catch (\Throwable $e) {
        }
        return $defaults;
    }

    public static function set(string $key, array $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            [
                'k' => $key,
                'v' => $json,
                't' => date('c'),
                'v2' => $json,
                't2' => date('c'),
            ]
        );
    }

    public static function tokens(): array
    {
        return self::get(self::TOKEN_KEY, self::defaultTokens());
    }

    public static function layout(): array
    {
        return self::get(self::LAYOUT_KEY, self::defaultLayout());
    }

    public static function components(): array
    {
        return self::get(self::COMPONENTS_KEY, self::defaultComponents());
    }

    public static function saveTokens(array $tokens): void
    {
        self::set(self::TOKEN_KEY, self::mergeDeep(self::defaultTokens(), $tokens));
    }

    public static function saveLayout(array $layout): void
    {
        self::set(self::LAYOUT_KEY, self::mergeDeep(self::defaultLayout(), $layout));
    }

    public static function saveComponents(array $components): void
    {
        self::set(self::COMPONENTS_KEY, self::mergeDeep(self::defaultComponents(), $components));
    }

    /**
     * Emit CSS custom properties for public themes.
     */
    public static function cssVariables(): string
    {
        $t = self::tokens();
        $layout = self::layout();
        $c = self::components();

        $colors = $t['colors'] ?? [];
        $dark = $t['colors_dark'] ?? [];
        $typo = $t['typography'] ?? [];
        $radius = $t['radius'] ?? [];
        $shadows = $t['shadows'] ?? [];
        $spacing = $t['spacing'] ?? [];

        $scale = (float) ($typo['scale'] ?? 1);
        if ($scale < 0.8) {
            $scale = 0.8;
        }
        if ($scale > 1.25) {
            $scale = 1.25;
        }

        $container = $layout['container_width'] ?? '720px';

        $btnRadius = $radius[$c['button']['radius'] ?? 'md'] ?? ($radius['md'] ?? '10px');
        $cardRadius = $radius[$c['card']['radius'] ?? 'md'] ?? ($radius['md'] ?? '10px');

        // Palette values (fixed -light / -dark aliases always available)
        $L = [
            'primary' => self::cssVal($colors['primary'] ?? '#2563eb'),
            'secondary' => self::cssVal($colors['secondary'] ?? '#64748b'),
            'accent' => self::cssVal($colors['accent'] ?? '#7c3aed'),
            'bg' => self::cssVal($colors['background'] ?? '#f8f9fb'),
            'surface' => self::cssVal($colors['surface'] ?? '#ffffff'),
            'text' => self::cssVal($colors['text'] ?? '#111827'),
            'muted' => self::cssVal($colors['muted'] ?? '#6b7280'),
            'border' => self::cssVal($colors['border'] ?? '#e5e7eb'),
        ];
        $D = [
            'primary' => self::cssVal($dark['primary'] ?? '#60a5fa'),
            'secondary' => self::cssVal($dark['secondary'] ?? '#94a3b8'),
            'accent' => self::cssVal($dark['accent'] ?? '#a78bfa'),
            'bg' => self::cssVal($dark['background'] ?? '#0b0d12'),
            'surface' => self::cssVal($dark['surface'] ?? '#12151c'),
            'text' => self::cssVal($dark['text'] ?? '#f3f4f6'),
            'muted' => self::cssVal($dark['muted'] ?? '#9ca3af'),
            'border' => self::cssVal($dark['border'] ?? '#1f2430'),
        ];

        $lines = [];

        // Explicit light/dark palette tokens (always the same regardless of active theme)
        $lines[] = ':root {';
        foreach (['primary', 'secondary', 'accent', 'bg', 'surface', 'text', 'muted', 'border'] as $k) {
            $lines[] = '  --color-' . $k . '-light: ' . $L[$k] . ';';
            $lines[] = '  --color-' . $k . '-dark: ' . $D[$k] . ';';
        }
        // Aliases matching Style / Variables naming
        $lines[] = '  --color-background-light: ' . $L['bg'] . ';';
        $lines[] = '  --color-background-dark: ' . $D['bg'] . ';';
        // Non-color design tokens
        $lines[] = '  --font-sans: ' . ($typo['font_sans'] ?? 'system-ui, sans-serif') . ';';
        $lines[] = '  --font-mono: ' . ($typo['font_mono'] ?? 'ui-monospace, monospace') . ';';
        $lines[] = '  --font-scale: ' . $scale . ';';
        $lines[] = '  --max-width: ' . self::cssVal($container) . ';';
        $lines[] = '  --radius: ' . self::cssVal($radius['md'] ?? '10px') . ';';
        $lines[] = '  --radius-sm: ' . self::cssVal($radius['sm'] ?? '6px') . ';';
        $lines[] = '  --radius-md: ' . self::cssVal($radius['md'] ?? '10px') . ';';
        $lines[] = '  --radius-lg: ' . self::cssVal($radius['lg'] ?? '16px') . ';';
        $lines[] = '  --radius-full: ' . self::cssVal($radius['full'] ?? '999px') . ';';
        $lines[] = '  --radius-button: ' . self::cssVal($btnRadius) . ';';
        $lines[] = '  --radius-card: ' . self::cssVal($cardRadius) . ';';
        $lines[] = '  --shadow-sm: ' . ($shadows['sm'] ?? '0 1px 2px rgba(0,0,0,0.04)') . ';';
        $lines[] = '  --shadow-md: ' . ($shadows['md'] ?? '0 4px 12px rgba(0,0,0,0.08)') . ';';
        $lines[] = '  --space-section: ' . self::cssVal($spacing['section'] ?? '2.5rem') . ';';
        $lines[] = '  --space-element: ' . self::cssVal($spacing['element'] ?? '1.5rem') . ';';
        $lines[] = '}';

        // Theme-aware tokens (use these in content CSS — they flip with the toggle)
        // html[data-theme] beats :root specificity so dark mode always wins.
        $lines[] = ':root, html[data-theme="light"] {';
        $lines[] = '  --color-primary: ' . $L['primary'] . ';';
        $lines[] = '  --color-accent: ' . $L['primary'] . ';';
        $lines[] = '  --color-accent-hover: ' . $L['primary'] . ';';
        $lines[] = '  --color-secondary: ' . $L['secondary'] . ';';
        $lines[] = '  --color-brand-accent: ' . $L['accent'] . ';';
        $lines[] = '  --color-bg: ' . $L['bg'] . ';';
        $lines[] = '  --color-background: ' . $L['bg'] . ';';
        $lines[] = '  --color-surface: ' . $L['surface'] . ';';
        $lines[] = '  --color-text: ' . $L['text'] . ';';
        $lines[] = '  --color-muted: ' . $L['muted'] . ';';
        $lines[] = '  --color-border: ' . $L['border'] . ';';
        $lines[] = '  --color-quote-border: ' . $L['primary'] . ';';
        $lines[] = '  --color-on-primary: ' . self::contrastOn($L['primary']) . ';';
        $lines[] = '}';

        $lines[] = 'html[data-theme="dark"] {';
        $lines[] = '  --color-primary: ' . $D['primary'] . ';';
        $lines[] = '  --color-accent: ' . $D['primary'] . ';';
        $lines[] = '  --color-accent-hover: ' . $D['primary'] . ';';
        $lines[] = '  --color-secondary: ' . $D['secondary'] . ';';
        $lines[] = '  --color-brand-accent: ' . $D['accent'] . ';';
        $lines[] = '  --color-bg: ' . $D['bg'] . ';';
        $lines[] = '  --color-background: ' . $D['bg'] . ';';
        $lines[] = '  --color-surface: ' . $D['surface'] . ';';
        $lines[] = '  --color-text: ' . $D['text'] . ';';
        $lines[] = '  --color-muted: ' . $D['muted'] . ';';
        $lines[] = '  --color-border: ' . $D['border'] . ';';
        $lines[] = '  --color-quote-border: ' . $D['primary'] . ';';
        $lines[] = '  --color-on-primary: ' . self::contrastOn($D['primary']) . ';';
        $lines[] = '}';

        // User-defined custom colors (Style → custom swatches)
        $customColors = $t['custom_colors'] ?? [];
        if (is_array($customColors) && $customColors) {
            $lines[] = ':root {';
            foreach ($customColors as $cc) {
                if (!is_array($cc)) {
                    continue;
                }
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($cc['slug'] ?? ''))) ?? '';
                if ($slug === '') {
                    continue;
                }
                $lv = self::cssVal((string) ($cc['light'] ?? '#ffffff'));
                $dv = self::cssVal((string) ($cc['dark'] ?? '#ffffff'));
                $lines[] = '  --color-' . $slug . '-light: ' . $lv . ';';
                $lines[] = '  --color-' . $slug . '-dark: ' . $dv . ';';
            }
            $lines[] = '}';
            $lines[] = ':root, html[data-theme="light"] {';
            foreach ($customColors as $cc) {
                if (!is_array($cc)) {
                    continue;
                }
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($cc['slug'] ?? ''))) ?? '';
                if ($slug === '') {
                    continue;
                }
                $lines[] = '  --color-' . $slug . ': ' . self::cssVal((string) ($cc['light'] ?? '#ffffff')) . ';';
            }
            $lines[] = '}';
            $lines[] = 'html[data-theme="dark"] {';
            foreach ($customColors as $cc) {
                if (!is_array($cc)) {
                    continue;
                }
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($cc['slug'] ?? ''))) ?? '';
                if ($slug === '') {
                    continue;
                }
                $lines[] = '  --color-' . $slug . ': ' . self::cssVal((string) ($cc['dark'] ?? '#ffffff')) . ';';
            }
            $lines[] = '}';
        }

        // Component data attributes as CSS hooks
        $btnVariant = preg_replace('/[^a-z0-9\-]/', '', (string) ($c['button']['variant'] ?? 'solid')) ?: 'solid';
        $cardVariant = preg_replace('/[^a-z0-9\-]/', '', (string) ($c['card']['variant'] ?? 'elevated')) ?: 'elevated';
        $heroVariant = preg_replace('/[^a-z0-9\-]/', '', (string) ($c['hero']['variant'] ?? 'centered')) ?: 'centered';
        $cardHover = preg_replace('/[^a-z0-9\-]/', '', (string) ($c['card']['hover'] ?? 'lift')) ?: 'lift';

        $lines[] = 'body {';
        $lines[] = '  --btn-variant: ' . $btnVariant . ';';
        $lines[] = '  --card-variant: ' . $cardVariant . ';';
        $lines[] = '  --hero-variant: ' . $heroVariant . ';';
        $lines[] = '  --card-hover: ' . $cardHover . ';';
        $lines[] = '  font-size: calc(16px * var(--font-scale, 1));';
        $lines[] = '}';

        // User / custom design variables (Design → Variables)
        if (class_exists(VariableService::class)) {
            $lines[] = VariableService::customCssVariables();
        }

        return implode("\n", $lines);
    }

    /** Safe CSS value for colors / lengths (no braces, semicolons) */
    /**
     * Text/icon color that contrasts with a background hex (for primary buttons, headers).
     */
    public static function contrastOn(string $hex): string
    {
        $hex = trim($hex);
        if (preg_match('/^#([0-9A-Fa-f]{3})$/', $hex, $m)) {
            $h = $m[1];
            $hex = '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (!preg_match('/^#([0-9A-Fa-f]{6})$/', $hex, $m)) {
            return '#ffffff';
        }
        $n = $m[1];
        $r = hexdec(substr($n, 0, 2)) / 255;
        $g = hexdec(substr($n, 2, 2)) / 255;
        $b = hexdec(substr($n, 4, 2)) / 255;
        $lin = static function (float $v): float {
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };
        $L = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
        return $L > 0.55 ? '#111827' : '#ffffff';
    }

    private static function cssVal(string $v): string
    {
        $v = trim($v);
        $v = str_replace([';', '{', '}', '<', '>'], '', $v);
        return $v !== '' ? $v : 'inherit';
    }

    private static function mergeDeep(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = self::mergeDeep($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    /** @var array<string, mixed> */
    private static array $settingCache = [];

    public static function setting(string $key, $default = null)
    {
        if (!array_key_exists($key, self::$settingCache)) {
            try {
                $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = :k", ['k' => $key]);
                self::$settingCache[$key] = $row['setting_value'] ?? $default;
            } catch (\Throwable $e) {
                self::$settingCache[$key] = $default;
            }
        }
        return self::$settingCache[$key] ?? $default;
    }

    public static function saveSetting(string $key, string $value): void
    {
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            ['k' => $key, 'v' => $value, 't' => date('c'), 'v2' => $value, 't2' => date('c')]
        );
        self::$settingCache[$key] = $value;
    }

    /**
     * HTML for the mobile menu toggle icon (Design → Layout settings).
     * Presets use inline SVG so the icon always shows even if Font Awesome CDN fails.
     *
     * @return array{html: string, need_fa: bool}
     */
    public static function mobileMenuIcon(): array
    {
        $mode = (string) self::setting('mobile_menu_icon_mode', 'preset');
        $preset = (string) self::setting('mobile_menu_icon_preset', 'fa-bars');
        $custom = trim((string) self::setting('mobile_menu_icon_custom', ''));
        $url = trim((string) self::setting('mobile_menu_icon_url', ''));

        if ($mode === 'upload' && $url !== '') {
            return [
                'html' => '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" class="nav-toggle-img" width="22" height="22">',
                'need_fa' => false,
            ];
        }

        if ($mode === 'custom' && $custom !== '') {
            // Allow "fa-solid fa-bars" or bare "fa-bars"
            $cls = $custom;
            if (strpos($cls, 'fa-') !== false && strpos($cls, 'fa-solid') === false
                && strpos($cls, 'fa-regular') === false && strpos($cls, 'fa-brands') === false
                && strpos($cls, 'fa-light') === false) {
                $cls = 'fa-solid ' . $cls;
            }
            return [
                'html' => '<i class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>',
                'need_fa' => true,
            ];
        }

        // Preset → inline SVG (never depends on external icon font)
        $svgs = [
            'fa-bars' => '<svg class="nav-toggle-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
            'fa-bars-staggered' => '<svg class="nav-toggle-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M8 12h12M4 18h10"/></svg>',
            'fa-ellipsis-vertical' => '<svg class="nav-toggle-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="12" cy="19" r="1.75"/></svg>',
            'fa-grip-lines' => '<svg class="nav-toggle-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 9h16M4 15h16"/></svg>',
            'fa-align-justify' => '<svg class="nav-toggle-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
        ];
        // Normalize if HQ saved a full class string
        $presetKey = preg_replace('/^fa-(solid|regular|brands|light)\s+/', '', $preset) ?? $preset;
        $presetKey = trim($presetKey);
        if (!isset($svgs[$presetKey])) {
            $presetKey = 'fa-bars';
        }

        return [
            'html' => $svgs[$presetKey],
            'need_fa' => false,
        ];
    }
}
