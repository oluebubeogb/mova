<?php
/**
 * Mova\Site\Pwa — installable web app (PWA) support for the public site.
 *
 * Everything is served by PHP from the public front controller (no build step, works on
 * shared hosting): /manifest.webmanifest, /sw.js (root scope), /pwa-offline and the
 * /pwa-icon-*.png icons generated with GD from the site logo (graceful fallback without GD).
 *
 * Settings (settings table, managed from HQ → Extend → App (PWA)):
 *   pwa_enabled, pwa_name, pwa_short_name, pwa_theme_color, pwa_bg_color, pwa_icon,
 *   pwa_display, pwa_start_url, pwa_banner, pwa_banner_msg, pwa_banner_trigger,
 *   pwa_banner_delay, pwa_banner_pageviews, pwa_banner_snooze_days, pwa_offline, pwa_rev
 * Blank name / colours / icon fall back to the site name, brand colour, theme background and logo.
 */

declare(strict_types=1);

namespace Mova\Site;

use Mova\Core\Bootstrap;
use Mova\Core\Database;

final class Pwa
{
    public const KEYS = [
        'pwa_enabled', 'pwa_name', 'pwa_short_name', 'pwa_theme_color', 'pwa_bg_color', 'pwa_icon',
        'pwa_display', 'pwa_start_url', 'pwa_banner', 'pwa_banner_msg', 'pwa_banner_trigger',
        'pwa_banner_delay', 'pwa_banner_pageviews', 'pwa_banner_snooze_days', 'pwa_offline',
    ];
    public const DISPLAYS = ['standalone', 'minimal-ui', 'fullscreen', 'browser'];
    public const ICON_SIZES = ['192' => 192, '512' => 512, 'maskable' => 512, '180' => 180];
    public const PATHS = ['/manifest.webmanifest', '/sw.js', '/pwa-offline', '/pwa-icon.svg',
        '/pwa-icon-192.png', '/pwa-icon-512.png', '/pwa-icon-maskable.png', '/pwa-icon-180.png'];

    private static ?array $raw = null;
    private static ?array $cfg = null;

    // ------------------------------------------------------------------ settings

    /** Raw stored values (pwa_* plus the site keys we derive defaults from). */
    public static function raw(): array
    {
        if (self::$raw !== null) {
            return self::$raw;
        }
        $out = [];
        try {
            $rows = Database::fetchAll(
                "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'pwa_%'
                 OR setting_key IN ('site_name','site_description','logo_url','favicon_url','brand_color')"
            );
            foreach ($rows as $r) {
                $out[(string) $r['setting_key']] = (string) $r['setting_value'];
            }
        } catch (\Throwable $e) {
        }
        return self::$raw = $out;
    }

    public static function reset(): void
    {
        self::$raw = null;
        self::$cfg = null;
    }

    public static function enabled(): bool
    {
        return (self::raw()['pwa_enabled'] ?? '0') === '1';
    }

    private static function hex(string $v): ?string
    {
        $v = trim($v);
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) ? strtolower($v) : null;
    }

    /** Defaults derived from the site (used to prefill HQ and when a field is blank). */
    public static function defaults(): array
    {
        $r = self::raw();
        $tokens = [];
        try {
            $tokens = \Mova\Theme\DesignConfig::tokens();
        } catch (\Throwable $e) {
        }
        $name = trim((string) ($r['site_name'] ?? '')) ?: (string) Bootstrap::config('app_name', 'Mova');
        $short = self::shorten($name);
        $theme = self::hex((string) ($r['brand_color'] ?? '')) ?? self::hex((string) ($tokens['colors']['primary'] ?? '')) ?? '#2563eb';
        $bg = self::hex((string) ($tokens['colors']['background'] ?? '')) ?? '#ffffff';
        $logo = trim((string) ($r['logo_url'] ?? ''));
        if ($logo === '') {
            $logo = trim((string) ($r['favicon_url'] ?? ''));
        }
        return [
            'name' => $name,
            'short_name' => $short,
            'description' => trim((string) ($r['site_description'] ?? '')),
            'theme_color' => $theme,
            'bg_color' => $bg,
            'icon' => $logo,
            'display' => 'standalone',
            'start_url' => '/',
            'banner' => true,
            'banner_msg' => 'Install our app for faster access, even offline.',
            'banner_trigger' => 'delay',
            'banner_delay' => 8,
            'banner_pageviews' => 2,
            'banner_snooze_days' => 14,
            'offline' => true,
        ];
    }

    public static function shorten(string $name): string
    {
        $name = trim($name);
        if (mb_strlen($name) <= 12) {
            return $name;
        }
        $words = preg_split('/\s+/', $name) ?: [$name];
        $out = '';
        foreach ($words as $w) {
            if (mb_strlen(trim($out . ' ' . $w)) > 12) {
                break;
            }
            $out = trim($out . ' ' . $w);
        }
        return $out !== '' ? $out : mb_substr($name, 0, 12);
    }

    /** Effective configuration (stored values, blanks → defaults, validated). */
    public static function config(): array
    {
        if (self::$cfg !== null) {
            return self::$cfg;
        }
        $r = self::raw();
        $d = self::defaults();
        $s = static fn(string $k) => trim((string) ($r['pwa_' . $k] ?? ''));
        $int = static function (string $k, int $def, int $min, int $max) use ($r): int {
            $v = $r['pwa_' . $k] ?? '';
            if ($v === '' || !is_numeric($v)) {
                return $def;
            }
            return max($min, min($max, (int) $v));
        };
        $display = $s('display');
        $start = self::cleanStartUrl($s('start_url'));
        $trigger = $s('banner_trigger');
        return self::$cfg = [
            'enabled' => self::enabled(),
            'name' => $s('name') !== '' ? $s('name') : $d['name'],
            'short_name' => $s('short_name') !== '' ? $s('short_name') : $d['short_name'],
            'description' => $d['description'],
            'theme_color' => self::hex($s('theme_color')) ?? $d['theme_color'],
            'bg_color' => self::hex($s('bg_color')) ?? $d['bg_color'],
            'icon' => $s('icon') !== '' ? $s('icon') : $d['icon'],
            'display' => in_array($display, self::DISPLAYS, true) ? $display : 'standalone',
            'start_url' => $start !== '' ? $start : '/',
            'banner' => ($r['pwa_banner'] ?? '1') === '1',
            'banner_msg' => $s('banner_msg') !== '' ? $s('banner_msg') : $d['banner_msg'],
            'banner_trigger' => in_array($trigger, ['delay', 'pageviews'], true) ? $trigger : 'delay',
            'banner_delay' => $int('banner_delay', 8, 0, 600),
            'banner_pageviews' => $int('banner_pageviews', 2, 1, 50),
            'banner_snooze_days' => $int('banner_snooze_days', 14, 0, 365),
            'offline' => ($r['pwa_offline'] ?? '1') === '1',
            'rev' => (string) ($r['pwa_rev'] ?? '0'),
        ];
    }

    /** Same-origin path only (no scheme/host, no /hq or /api). */
    public static function cleanStartUrl(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $v)) {
            $p = parse_url($v);
            $v = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
        }
        if ($v[0] !== '/' || strpos($v, '//') === 0) {
            $v = '/' . ltrim($v, '/');
        }
        if (preg_match('~^/(hq|api)(/|$|\?)~', $v)) {
            return '/';
        }
        return preg_replace('~[\s<>"\']~', '', $v) ?? '/';
    }

    /** Persist HQ form values (already a key => value map of pwa_* keys). Bumps pwa_rev. */
    public static function save(array $values): void
    {
        $up = static function (string $k, string $v): void {
            Database::query(
                "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
                ['k' => $k, 'v' => $v, 't' => date('c'), 'v2' => $v, 't2' => date('c')]
            );
        };
        foreach ($values as $k => $v) {
            if (in_array($k, self::KEYS, true)) {
                $up($k, (string) $v);
            }
        }
        $up('pwa_rev', (string) time());
        self::reset();
        try {
            self::generateIcons(true);
        } catch (\Throwable $e) {
        }
        try {
            \Mova\Cache\PageCache::flush(); // head tags live in cached pages
        } catch (\Throwable $e) {
        }
    }

    /**
     * Cache version: changes whenever PWA settings, any content, site settings or the theme
     * assets change, so the service worker script bytes change and old caches are purged.
     */
    public static function version(): string
    {
        $parts = [self::config()['rev']];
        try {
            $row = Database::fetch("SELECT MAX(updated_at) AS m, COUNT(*) AS c FROM content");
            $parts[] = (string) ($row['m'] ?? '') . '#' . (string) ($row['c'] ?? '');
        } catch (\Throwable $e) {
        }
        try {
            $row = Database::fetch("SELECT MAX(updated_at) AS m FROM settings");
            $parts[] = (string) ($row['m'] ?? '');
        } catch (\Throwable $e) {
        }
        try {
            $parts[] = \Mova\Theme\SiteFrame::assetVersion();
        } catch (\Throwable $e) {
        }
        $js = dirname(__DIR__, 2) . '/public/assets/js/pwa.js';
        $parts[] = is_file($js) ? (string) filemtime($js) : '';
        return substr(sha1(implode('|', $parts)), 0, 10);
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on');
    }

    /** Browsers treat localhost as a secure context, so PWAs work there without TLS. */
    public static function isSecureContext(): bool
    {
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        return self::isHttps() || in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || str_ends_with($host, '.localhost');
    }

    // ------------------------------------------------------------------ icons

    public static function gdAvailable(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    private static function iconDir(): string
    {
        $dir = rtrim(Bootstrap::path('storage'), '/') . '/pwa';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Local file for a site-relative URL like /mova-uploads/logo.png (null if remote / missing). */
    public static function localFile(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $url)) {
            $base = Bootstrap::baseUrl();
            if (stripos($url, $base) !== 0) {
                return null;
            }
            $url = substr($url, strlen($base));
        }
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        if ($path === '' || strpos($path, '..') !== false) {
            return null;
        }
        $roots = [dirname(__DIR__, 2) . '/public', dirname(__DIR__, 2), (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')];
        foreach ($roots as $root) {
            if ($root !== '' && is_file($root . $path)) {
                return $root . $path;
            }
        }
        return null;
    }

    private static function iconStamp(): string
    {
        $c = self::config();
        $src = self::localFile($c['icon']);
        return sha1(implode('|', [$c['icon'], $src ? (string) @filemtime($src) : '-', $c['theme_color'], $c['bg_color'], $c['name'], 'v2']));
    }

    /** Generate the 192 / 512 / maskable-512 / 180 PNGs. Returns false when GD is missing. */
    public static function generateIcons(bool $force = false): bool
    {
        if (!self::gdAvailable()) {
            return false;
        }
        $dir = self::iconDir();
        $stamp = self::iconStamp();
        $stampFile = $dir . '/stamp.txt';
        if (!$force && is_file($stampFile) && trim((string) @file_get_contents($stampFile)) === $stamp && is_file($dir . '/icon-512.png')) {
            return true;
        }
        $c = self::config();
        $src = null;
        $file = self::localFile($c['icon']);
        if ($file && !preg_match('/\.svg$/i', $file)) {
            $data = @file_get_contents($file);
            $src = $data !== false ? @imagecreatefromstring($data) : false;
            $src = $src ?: null;
            if ($src && (imagesx($src) < 48 || imagesy($src) < 48)) {
                imagedestroy($src); // too small to make a usable icon: use the lettermark instead
                $src = null;
            }
        }
        foreach (self::ICON_SIZES as $key => $size) {
            $mask = ($key === 'maskable' || $key === '180');
            $im = self::renderIcon($size, $src, $c, $mask);
            imagepng($im, $dir . '/icon-' . $key . '.png', 6);
            imagedestroy($im);
        }
        if ($src) {
            imagedestroy($src);
        }
        @file_put_contents($stampFile, $stamp);
        return true;
    }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    /**
     * "any" icons: a rounded tile in the theme colour (logo on the background colour when the logo
     * exists). Maskable / apple: full-bleed square, artwork inside the 80% safe zone.
     */
    private static function renderIcon(int $size, $src, array $c, bool $fullBleed)
    {
        $ss = 2; // supersample for smooth rounded corners
        $S = $size * $ss;
        $im = imagecreatetruecolor($S, $S);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        $tileHex = $src ? $c['bg_color'] : $c['theme_color'];
        [$r, $g, $b] = self::rgb($tileHex);
        $tile = imagecolorallocate($im, $r, $g, $b);
        if ($fullBleed) {
            imagefilledrectangle($im, 0, 0, $S, $S, $tile);
        } else {
            $rad = (int) round($S * 0.22);
            imagefilledrectangle($im, $rad, 0, $S - $rad, $S, $tile);
            imagefilledrectangle($im, 0, $rad, $S, $S - $rad, $tile);
            foreach ([[$rad, $rad], [$S - $rad - 1, $rad], [$rad, $S - $rad - 1], [$S - $rad - 1, $S - $rad - 1]] as [$cx, $cy]) {
                imagefilledellipse($im, $cx, $cy, $rad * 2, $rad * 2, $tile);
            }
        }
        $box = (int) round($S * ($fullBleed ? 0.58 : 0.66)); // maskable safe zone = inner 80% circle
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min($box / max(1, $w), $box / max(1, $h));
            $dw = max(1, (int) round($w * $scale));
            $dh = max(1, (int) round($h * $scale));
            imagecopyresampled($im, $src, (int) (($S - $dw) / 2), (int) (($S - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
        } else {
            self::drawInitial($im, $S, $c);
        }
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $S, $S);
        imagedestroy($im);
        return $out;
    }

    private static function drawInitial($im, int $S, array $c): void
    {
        $ch = mb_strtoupper(mb_substr(trim((string) $c['name']) ?: 'M', 0, 1));
        [$r, $g, $b] = self::rgb($c['theme_color']);
        $lum = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        $fg = $lum > 0.62 ? imagecolorallocate($im, 17, 24, 39) : imagecolorallocate($im, 255, 255, 255);
        $font = dirname(__DIR__, 2) . '/resources/fonts/inter-bold-latin.ttf';
        if (function_exists('imagettftext') && is_file($font)) {
            $pt = $S * 0.40;
            $bb = imagettfbbox($pt, 0, $font, $ch);
            if ($bb) {
                $tw = $bb[2] - $bb[0];
                $th = $bb[1] - $bb[7];
                $x = (int) round(($S - $tw) / 2 - $bb[0]);
                $y = (int) round(($S + $th) / 2 - $bb[1]);
                imagettftext($im, $pt, 0, $x, $y, $fg, $font, $ch);
                return;
            }
        }
        // No FreeType: scale up GD's built-in bitmap font.
        $gw = imagefontwidth(5);
        $gh = imagefontheight(5);
        $t = imagecreatetruecolor($gw, $gh);
        imagealphablending($t, false);
        imagesavealpha($t, true);
        imagefill($t, 0, 0, imagecolorallocatealpha($t, 0, 0, 0, 127));
        imagestring($t, 5, 0, 0, $ch, $fg);
        $h = (int) round($S * 0.5);
        $w = (int) round($h * $gw / $gh);
        imagecopyresampled($im, $t, (int) (($S - $w) / 2), (int) (($S - $h) / 2), 0, 0, $w, $h, $gw, $gh);
        imagedestroy($t);
    }

    /** SVG icon (used when GD is unavailable, and as a scalable extra icon). */
    public static function svgIcon(): string
    {
        $c = self::config();
        $ch = htmlspecialchars(mb_strtoupper(mb_substr(trim((string) $c['name']) ?: 'M', 0, 1)), ENT_XML1);
        [$r, $g, $b] = self::rgb($c['theme_color']);
        $fg = ((0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255) > 0.62 ? '#111827' : '#ffffff';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect width="512" height="512" rx="112" fill="' . $c['theme_color'] . '"/>'
            . '<text x="256" y="256" dy=".35em" text-anchor="middle" font-family="Inter,Segoe UI,Helvetica,Arial,sans-serif" font-size="230" font-weight="700" fill="' . $fg . '">' . $ch . '</text></svg>';
    }

    /** Public URL of an icon, or null when that PNG cannot be produced (no GD). */
    public static function iconUrl(string $key): ?string
    {
        if (!self::gdAvailable()) {
            return null;
        }
        return '/pwa-icon-' . $key . '.png?v=' . substr(self::iconStamp(), 0, 8);
    }

    // ------------------------------------------------------------------ manifest / head

    public static function manifest(): array
    {
        $c = self::config();
        $icons = [];
        if (self::gdAvailable()) {
            $icons[] = ['src' => self::iconUrl('192'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => self::iconUrl('512'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => self::iconUrl('maskable'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'];
        } else {
            // Graceful fallback: a raster logo as-is (browser scales it) plus the SVG initial.
            $file = self::localFile($c['icon']);
            if ($file && ($info = @getimagesize($file)) && !empty($info[0])) {
                $icons[] = ['src' => $c['icon'], 'sizes' => $info[0] . 'x' . $info[1], 'type' => (string) $info['mime'], 'purpose' => 'any'];
            }
            $icons[] = ['src' => '/pwa-icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'];
        }
        $m = [
            'id' => '/',
            'name' => $c['name'],
            'short_name' => $c['short_name'],
            'start_url' => $c['start_url'],
            'scope' => '/',
            'display' => $c['display'],
            'background_color' => $c['bg_color'],
            'theme_color' => $c['theme_color'],
            'lang' => 'en',
            'dir' => 'ltr',
            'icons' => $icons,
        ];
        if ($c['description'] !== '') {
            $m['description'] = $c['description'];
        }
        return $m;
    }

    /** Head tags for every theme (called from resources/theme-parts/head.php). */
    public static function headHtml(): string
    {
        $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        if (!self::enabled()) {
            // Disabled: cleanly remove a worker registered while PWA was on (flag set by pwa.js).
            // The flag is only cleared on a page that is no longer controlled and has nothing left to
            // remove, so a cache written by the old worker during its last page view is also cleaned.
            return "    <script>(function(){try{var sw=navigator.serviceWorker;if(!sw||!localStorage.getItem('mova_pwa_sw'))return;"
                . "addEventListener('load',function(){var found=!!sw.controller;"
                . "Promise.all([sw.getRegistrations().then(function(rs){return Promise.all(rs.map(function(r){var w=r.active||r.waiting||r.installing;if(w&&/\\/sw\\.js(\\?|$)/.test(w.scriptURL)){found=true;return r.unregister();}}));}),"
                . "window.caches?caches.keys().then(function(ks){return Promise.all(ks.filter(function(k){return k.indexOf('mova-')===0;}).map(function(k){found=true;return caches.delete(k);}));}):0"
                . "]).then(function(){if(!found)localStorage.removeItem('mova_pwa_sw');});});}catch(e){}})();</script>\n";
        }
        $c = self::config();
        $v = self::version();
        $apple = self::iconUrl('180') ?? '/pwa-icon.svg';
        $js = [
            'name' => $c['name'],
            'short' => $c['short_name'],
            'msg' => $c['banner_msg'],
            'icon' => self::iconUrl('192') ?? '/pwa-icon.svg',
            'banner' => $c['banner'],
            'trigger' => $c['banner_trigger'],
            'delay' => $c['banner_delay'],
            'pageviews' => $c['banner_pageviews'],
            'snooze' => $c['banner_snooze_days'],
            'sw' => '/sw.js',
            'v' => $v,
        ];
        $h = '';
        $h .= '    <link rel="manifest" href="/manifest.webmanifest?v=' . $e($v) . '">' . "\n";
        $h .= '    <meta name="mobile-web-app-capable" content="yes">' . "\n";
        $h .= '    <meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        $h .= '    <meta name="apple-mobile-web-app-title" content="' . $e($c['short_name']) . '">' . "\n";
        $h .= '    <meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
        $h .= '    <link rel="apple-touch-icon" sizes="180x180" href="' . $e($apple) . '">' . "\n";
        if ($c['banner']) {
            $h .= '    <link rel="stylesheet" href="/assets/css/pwa.css?v=' . $e($v) . '">' . "\n";
        }
        $h .= '    <script>window.MovaPWA=' . json_encode($js, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>' . "\n";
        $h .= '    <script src="/assets/js/pwa.js?v=' . $e($v) . '" defer></script>' . "\n";
        return $h;
    }

    // ------------------------------------------------------------------ public endpoints

    public static function handles(string $path): bool
    {
        return in_array($path, self::PATHS, true);
    }

    /** Serve a PWA endpoint and exit. */
    public static function serve(string $path): void
    {
        $send = static function (int $code, string $type, string $body, array $extra = []): void {
            http_response_code($code);
            header('Content-Type: ' . $type);
            header('X-Content-Type-Options: nosniff');
            foreach ($extra as $k => $v) {
                header($k . ': ' . $v);
            }
            echo $body;
            exit;
        };
        $c = self::config();

        if ($path === '/sw.js') {
            $hdr = ['Cache-Control' => 'no-cache, no-store, must-revalidate', 'Service-Worker-Allowed' => '/'];
            $send(200, 'application/javascript; charset=utf-8', $c['enabled'] ? self::serviceWorker() : self::killSwitch(), $hdr);
        }
        // Icons stay available while disabled (HQ preview uses them); manifest + offline page do not.
        if (!$c['enabled'] && !str_starts_with($path, '/pwa-icon')) {
            $send(404, 'text/plain; charset=utf-8', 'Not found', ['Cache-Control' => 'no-store']);
        }
        if ($path === '/manifest.webmanifest') {
            $send(200, 'application/manifest+json; charset=utf-8',
                (string) json_encode(self::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                ['Cache-Control' => 'public, max-age=300']);
        }
        if ($path === '/pwa-offline') {
            $send(200, 'text/html; charset=utf-8', self::offlineHtml(), ['Cache-Control' => 'public, max-age=300', 'X-Robots-Tag' => 'noindex']);
        }
        if ($path === '/pwa-icon.svg') {
            $send(200, 'image/svg+xml; charset=utf-8', self::svgIcon(), ['Cache-Control' => 'public, max-age=86400']);
        }
        if (preg_match('~^/pwa-icon-(192|512|maskable|180)\.png$~', $path, $m)) {
            if (!self::generateIcons()) {
                header('Location: /pwa-icon.svg', true, 302);
                exit;
            }
            $f = self::iconDir() . '/icon-' . $m[1] . '.png';
            if (!is_file($f)) {
                $send(404, 'text/plain', 'Not found');
            }
            $send(200, 'image/png', (string) file_get_contents($f), ['Cache-Control' => 'public, max-age=604800']);
        }
        $send(404, 'text/plain; charset=utf-8', 'Not found');
    }

    /** Self-contained offline page (inline CSS, no external assets — it must render offline). */
    public static function offlineHtml(): string
    {
        $c = self::config();
        $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $icon = self::iconUrl('192') ?? '/pwa-icon.svg';
        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="theme-color" content="' . $e($c['theme_color']) . '"><meta name="robots" content="noindex"><title>Offline · ' . $e($c['name']) . '</title>'
            . '<style>:root{color-scheme:light dark;--p:' . $e($c['theme_color']) . ';--bg:' . $e($c['bg_color']) . '}'
            . '*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bg);color:#111827;'
            . 'font:16px/1.55 Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
            . '@media (prefers-color-scheme:dark){body{background:#0b0d12;color:#f3f4f6}.card{background:#12151c!important;border-color:#1f2430!important}.muted{color:#9ca3af!important}}'
            . '.card{max-width:420px;width:100%;text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:36px 28px;box-shadow:0 20px 50px -20px rgba(0,0,0,.18)}'
            . 'img{width:72px;height:72px;border-radius:18px;margin:0 auto 18px;display:block}h1{font-size:1.35rem;margin:0 0 6px;letter-spacing:-.01em}'
            . '.muted{color:#6b7280;margin:0 0 22px}button{appearance:none;border:0;border-radius:999px;background:var(--p);color:#fff;font:600 15px/1 inherit;padding:13px 22px;cursor:pointer}'
            . 'button:focus-visible{outline:3px solid var(--p);outline-offset:3px}</style></head><body>'
            . '<main class="card" role="main"><img src="' . $e($icon) . '" alt="" width="72" height="72">'
            . '<h1>You’re offline</h1><p class="muted">' . $e($c['name']) . ' can’t be reached right now. Pages you’ve opened before are still available.</p>'
            . '<button type="button" onclick="location.reload()">Try again</button></main>'
            . '<script>addEventListener("online",function(){location.reload()});</script></body></html>';
    }

    public static function killSwitch(): string
    {
        return "/* Mova PWA is disabled: remove this worker and its caches. */\n"
            . "self.addEventListener('install',function(){self.skipWaiting();});\n"
            . "self.addEventListener('activate',function(e){e.waitUntil(caches.keys().then(function(ks){return Promise.all(ks.filter(function(k){return k.indexOf('mova-')===0;}).map(function(k){return caches.delete(k);}));}).then(function(){return self.registration.unregister();}));});\n";
    }

    public static function serviceWorker(): string
    {
        $c = self::config();
        $v = self::version();
        $offline = $c['offline'] ? '/pwa-offline' : '';
        $precache = array_values(array_filter([$offline, self::iconUrl('192')]));
        $cfg = json_encode(['v' => $v, 'offline' => $offline, 'precache' => $precache], JSON_UNESCAPED_SLASHES);
        $js = <<<'JS'
/* Mova PWA service worker — generated by Mova\Site\Pwa. Scope: / */
'use strict';
var CFG = __CFG__;
var PAGES = 'mova-pages-' + CFG.v, ASSETS = 'mova-assets-' + CFG.v;
var MAX_PAGES = 60;
var NEVER = /^\/(hq|api)(\/|$)|^\/(sw\.js|manifest\.webmanifest)$|^\/(feedback|unsubscribe|search|_preview)(\/|$)/;
var STATIC = /\.(?:css|js|mjs|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf|mp3|mp4|webm)$/i;

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(PAGES).then(function (c) { return c.addAll(CFG.precache); }).catch(function () {}).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) {
    return Promise.all(ks.filter(function (k) { return k.indexOf('mova-') === 0 && k !== PAGES && k !== ASSETS; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

function storable(res) {
  if (!res || !res.ok || res.type !== 'basic' || res.redirected) return false;
  if (res.headers.get('X-Mova-Private')) return false;          // signed-in visitor
  var cc = (res.headers.get('Cache-Control') || '').toLowerCase();
  return cc.indexOf('no-store') === -1 && cc.indexOf('private') === -1;
}
function trim(name) {
  return caches.open(name).then(function (c) { return c.keys().then(function (ks) {
    if (ks.length <= MAX_PAGES) return;
    return Promise.all(ks.slice(0, ks.length - MAX_PAGES).map(function (k) { return c.delete(k); }));
  }); });
}

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;                               // never POST etc.
  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (NEVER.test(url.pathname)) return;                           // HQ, API, the worker itself
  if (req.referrer) {                                             // anything requested by an HQ/API page
    try { if (/^\/(hq|api)(\/|$)/.test(new URL(req.referrer).pathname)) return; } catch (e) {}
  }
  if (/[?&](mova_theme|mova_sig|mx_static|preview)=/.test(url.search)) return;
  if (req.headers.get('range')) return;

  var isPage = req.mode === 'navigate' || (req.headers.get('accept') || '').indexOf('text/html') !== -1;
  if (isPage) {
    // Network-first for HTML, falling back to the cached copy, then the offline page.
    e.respondWith(fetch(req).then(function (res) {
      if (storable(res)) { var copy = res.clone(); caches.open(PAGES).then(function (c) { c.put(req, copy); trim(PAGES); }); }
      return res;
    }).catch(function () {
      return caches.match(req, { ignoreVary: true }).then(function (hit) {
        if (hit) return hit;
        if (CFG.offline) return caches.match(CFG.offline).then(function (o) { return o || Response.error(); });
        return Response.error();
      });
    }));
    return;
  }
  if (STATIC.test(url.pathname) || /^(style|script|image|font)$/.test(req.destination)) {
    // Cache-first for static assets (their URLs are versioned).
    e.respondWith(caches.match(req).then(function (hit) {
      return hit || fetch(req).then(function (res) {
        if (storable(res)) { var copy = res.clone(); caches.open(ASSETS).then(function (c) { c.put(req, copy); }); }
        return res;
      });
    }));
  }
});
self.addEventListener('message', function (e) { if (e.data === 'skipWaiting') self.skipWaiting(); });
JS;
        return str_replace('__CFG__', (string) $cfg, $js);
    }
}
