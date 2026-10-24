<?php
/**
 * SiteFrame — shared public-site chrome for every Mova theme.
 *
 * Themes keep a thin layout.php that calls SiteFrame::build(get_defined_vars(), [...])
 * and includes the shared partials from resources/theme-parts/ (head, header, footer, scripts).
 * That gives every theme the same correct nav, footer columns, SEO head, favicon, assembly
 * header/footer overrides, canvas (full-width section) pages, plugin hooks and dark mode,
 * while each theme only ships its own look (CSS + optional JS).
 */

namespace Mova\Theme;

use Mova\Core\Bootstrap;

final class SiteFrame
{
    /** Force site chrome on/off for preview renders (null = automatic). */
    public static ?bool $chrome = null;

    /** @var array<string,mixed> preview-only setting overrides (never persisted) */
    private static array $overrides = [];

    public static function overrides(): array
    {
        return self::$overrides;
    }

    public static function setOverrides(array $o): void
    {
        self::$overrides = $o + self::$overrides;
    }

    public static function partsPath(): string
    {
        return dirname(__DIR__, 2) . '/resources/theme-parts';
    }

    public static function setting(string $key, $default = null)
    {
        require_once __DIR__ . '/helpers.php';
        return mova_setting($key, $default);
    }

    /** Parse "Label|/url" lines. */
    public static function parseLinks(string $raw): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }
            [$label, $url] = array_map('trim', explode('|', $line, 2));
            if ($label !== '' && $url !== '') {
                $items[] = ['label' => $label, 'url' => $url];
            }
        }
        return $items;
    }

    public static function isCanvas($content): bool
    {
        if (!is_array($content)) {
            return false;
        }
        $m = $content['meta'] ?? [];
        if (($m['page_layout'] ?? '') === 'canvas') {
            return true;
        }
        // Auto: pages that are made of section-library blocks render full width.
        return ($m['page_layout'] ?? '') === '' && strpos((string) ($content['body'] ?? ''), 'class="mx-page') !== false;
    }

    /** Body HTML with plugin filters + responsive image upgrade (shared by all themes). */
    public static function body(array $content): string
    {
        $html = (string) ($content['body'] ?? '');
        if (class_exists(\Mova\Plugin\PluginManager::class)) {
            $html = (string) \Mova\Plugin\PluginManager::applyFilters('content.render.body', $html, $content);
        }
        if (class_exists(\Mova\Media\ImageTag::class)) {
            $html = \Mova\Media\ImageTag::upgradeBody($html);
        }
        return self::stripTokens($html);
    }

    /** Safety net: never show raw {{tokens}} to visitors. */
    public static function stripTokens(string $html): string
    {
        if (strpos($html, '{{') === false) {
            return $html;
        }
        $name = (string) self::setting('site_name', 'Mova');
        $html = str_replace(['{{site_name}}', '{{year}}'], [htmlspecialchars($name), date('Y')], $html);
        return preg_replace('/\{\{\s*[a-zA-Z0-9_.\-]+\s*\}\}/', '', $html) ?? $html;
    }

    public static function inlineFile(string $file, string $tag, string $id): string
    {
        if (!is_file($file)) {
            return '';
        }
        $src = (string) file_get_contents($file);
        return $tag === 'style'
            ? "<style id=\"" . htmlspecialchars($id) . "\">\n" . $src . "\n</style>\n"
            : "<script id=\"" . htmlspecialchars($id) . "\">\n" . $src . "\n</script>\n";
    }

    public static function assetVersion(): string
    {
        static $v = null;
        if ($v === null) {
            $f = dirname(__DIR__, 2) . '/public/assets/css/site.css';
            $v = is_file($f) ? (string) filemtime($f) : '1';
        }
        return $v;
    }

    /**
     * Build the frame context for a layout.
     * @param array $vars  get_defined_vars() from layout.php
     * @param array $opts  theme (slug), css (files to inline), js (files to inline), body_class
     */
    public static function build(array $vars, array $opts = []): array
    {
        require_once __DIR__ . '/helpers.php';
        $theme = (string) ($opts['theme'] ?? 'default');
        $content = is_array($vars['content'] ?? null) ? $vars['content'] : null;
        $seo = $vars['seo'] ?? null;
        $view = (string) ($vars['view'] ?? '');

        $siteName = (string) mova_setting('site_name', Bootstrap::config('app_name', 'Mova'));
        $siteDesc = (string) mova_setting('site_description', Bootstrap::config('app_tagline', ''));
        $logoUrl = (string) mova_setting('logo_url', '');
        $logoDark = (string) mova_setting('logo_url_dark', '');
        $favicon = (string) mova_setting('favicon_url', '');
        $brand = (string) mova_setting('brand_color', '');
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $brand)) {
            $tokens = class_exists(DesignConfig::class) ? DesignConfig::tokens() : [];
            $brand = (string) ($tokens['colors']['primary'] ?? '#2563eb');
        }
        $brandMode = (string) mova_setting('header_brand_mode', 'logo_and_name');
        if (!in_array($brandMode, ['logo_and_name', 'logo_only', 'name_only'], true)) {
            $brandMode = 'logo_and_name';
        }

        // Navigation
        $navRaw = mova_setting('nav_links', null);
        if ($navRaw === null && class_exists(NavDefaults::class)) {
            $navRaw = NavDefaults::raw();
        }
        $navAll = self::parseLinks((string) $navRaw);
        $deskRaw = trim((string) mova_setting('nav_links_desktop', ''));
        $deskSrc = $deskRaw !== '' ? self::parseLinks($deskRaw) : $navAll;
        $skip = static function (array $it): bool {
            $lab = strtolower(trim((string) $it['label']));
            $url = rtrim((string) $it['url'], '/');
            return $lab === 'home' || $url === '' || $lab === 'search' || $url === '/search';
        };
        $path = '/' . trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
        $mark = static function (array $items) use ($path, $skip): array {
            $out = [];
            foreach ($items as $it) {
                if ($skip($it)) {
                    continue;
                }
                $u = '/' . trim((string) parse_url($it['url'], PHP_URL_PATH), '/');
                $it['active'] = $u !== '/' && ($path === $u || strpos($path . '/', rtrim($u, '/') . '/') === 0);
                $it['external'] = (bool) preg_match('~^https?://~i', $it['url']) && stripos($it['url'], (string) ($_SERVER['HTTP_HOST'] ?? '#')) === false;
                $out[] = $it;
            }
            return $out;
        };
        $navDesktop = $mark($deskSrc);
        $navDrawer = $mark($navAll ?: $deskSrc);

        $cta = self::parseLinks((string) mova_setting('nav_cta', ''));
        $cta = $cta[0] ?? null;

        // Chrome / dev mode
        $useChrome = true;
        if ($content && (($content['meta']['editor_mode'] ?? '') === 'dev')) {
            $useChrome = (($content['meta']['use_site_chrome'] ?? '0') === '1');
        }
        if (self::$chrome !== null) {
            $useChrome = self::$chrome;
        }
        $canvas = ($view === 'content' && self::isCanvas($content));

        // Header / footer assemblies: page meta → parents → site default
        $hdrSlug = trim((string) mova_setting('header_assembly_slug', ''));
        $ftrSlug = trim((string) mova_setting('footer_assembly_slug', ''));
        if ($content && class_exists(\Mova\Content\ContentRepository::class)) {
            $repo = new \Mova\Content\ContentRepository();
            $walk = $content;
            $seen = [];
            $h = $f = null;
            for ($i = 0; $i < 12 && is_array($walk); $i++) {
                $cid = (int) ($walk['id'] ?? 0);
                if ($cid <= 0 || isset($seen[$cid])) {
                    break;
                }
                $seen[$cid] = true;
                $meta = is_array($walk['meta'] ?? null) ? $walk['meta'] : $repo->getMeta($cid);
                if ($h === null && trim((string) ($meta['header_assembly_slug'] ?? '')) !== '') {
                    $h = trim((string) $meta['header_assembly_slug']);
                }
                if ($f === null && trim((string) ($meta['footer_assembly_slug'] ?? '')) !== '') {
                    $f = trim((string) $meta['footer_assembly_slug']);
                }
                if ($h !== null && $f !== null) {
                    break;
                }
                $pid = (int) ($walk['parent_id'] ?? 0);
                $walk = $pid > 0 ? $repo->find($pid) : null;
            }
            $hdrSlug = $h ?? $hdrSlug;
            $ftrSlug = $f ?? $ftrSlug;
        }
        $hdrHtml = $ftrHtml = '';
        if (($hdrSlug !== '' || $ftrSlug !== '') && class_exists(\Mova\Assembly\AssemblyService::class)) {
            try {
                $asm = new \Mova\Assembly\AssemblyService();
                $rep = [htmlspecialchars($siteName), date('Y')];
                if ($hdrSlug !== '') {
                    $hdrHtml = str_replace(['{{site_name}}', '{{year}}'], $rep, (string) $asm->renderBySlug($hdrSlug));
                }
                if ($ftrSlug !== '') {
                    $ftrHtml = str_replace(['{{site_name}}', '{{year}}'], $rep, (string) $asm->renderBySlug($ftrSlug));
                }
            } catch (\Throwable $e) {
            }
        }

        // Footer columns (setup wizard / Design) → fallback: brand + explore
        $cols = [];
        $decoded = json_decode((string) mova_setting('footer_columns', '[]'), true);
        if (is_array($decoded)) {
            foreach ($decoded as $c) {
                if (is_array($c) && (!empty($c['title']) || !empty($c['text']) || !empty($c['links']))) {
                    // The brand block already shows name + tagline; skip a text-only column that repeats them.
                    $t = strtolower(trim(\Mova\Theme\SiteFrame::stripTokens((string) ($c['title'] ?? ''))));
                    if (empty($c['links']) && ($t === '' || $t === strtolower(trim((string) mova_setting('site_name', ''))))) {
                        continue;
                    }
                    $cols[] = $c;
                }
            }
        }
        $footerAuto = false;
        if (!$cols) {
            $footerAuto = true;
            $links = [];
            foreach (array_slice($navDrawer, 0, 6) as $it) {
                $links[] = ['label' => $it['label'], 'url' => $it['url']];
            }
            if ($links) {
                $cols[] = ['title' => 'Explore', 'links' => $links];
            }
        }

        // Design → layout / component switches (body classes)
        $layout = class_exists(DesignConfig::class) ? DesignConfig::layout() : [];
        $comp = class_exists(DesignConfig::class) ? DesignConfig::components() : [];
        $slugify = static fn($v, $d) => preg_replace('/[^a-z0-9\-]/', '', (string) $v) ?: $d;
        $hc = $layout['header'] ?? [];
        $fc = $layout['footer'] ?? [];
        $mn = $layout['mobile_nav'] ?? [];
        $mStyle = $slugify($mn['style'] ?? 'drawer-right', 'drawer-right');
        if ($mStyle === 'drawer') {
            $mStyle = 'drawer-top';
        }
        $mAlign = $slugify($mn['content_align'] ?? 'left', 'left');
        $classes = [
            'theme-' . $slugify($theme, 'default'), 'mx-site',
            'header-' . $slugify($hc['type'] ?? 'sticky', 'sticky'),
            'logo-' . $slugify($hc['logo_position'] ?? 'left', 'left'),
            'nav-' . $slugify($hc['nav_align'] ?? 'right', 'right'),
            'footer-' . $slugify($fc['style'] ?? 'simple', 'simple'),
            'mobile-nav-' . $mStyle,
            'mobile-nav-align-' . (in_array($mAlign, ['left', 'center', 'right'], true) ? $mAlign : 'left'),
            'btn-' . $slugify($comp['button']['variant'] ?? 'solid', 'solid'),
            'card-' . $slugify($comp['card']['variant'] ?? 'elevated', 'elevated'),
            'hero-' . $slugify($comp['hero']['variant'] ?? 'centered', 'centered'),
            'card-hover-' . $slugify($comp['card']['hover'] ?? 'lift', 'lift'),
            'view-' . $slugify($view, 'page'),
        ];
        if (!empty($comp['button']['uppercase'])) {
            $classes[] = 'btn-uppercase';
        }
        if (!empty($hc['transparent'])) {
            $classes[] = 'header-transparent';
        }
        if ($canvas) {
            $classes[] = 'is-canvas';
        }
        if ($view === 'home' || ($content && (int) ($content['id'] ?? 0) === (int) mova_setting('homepage_content_id', 0))) {
            $classes[] = 'is-home';
        }
        if (!empty($opts['body_class'])) {
            $classes[] = (string) $opts['body_class'];
        }

        $mobileIcon = '<svg class="nav-toggle-svg" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>';
        $needFa = false;
        if (class_exists(DesignConfig::class)) {
            $pack = DesignConfig::mobileMenuIcon();
            if (!empty($pack['html'])) {
                $mobileIcon = $pack['html'];
            }
            $needFa = !empty($pack['need_fa']);
        }

        $headHtml = '';
        if (is_object($seo) && method_exists($seo, 'renderHead')) {
            $ov = [];
            if (!empty($vars['title'])) {
                $ov['title'] = $vars['title'];
            }
            if (!$content && $siteDesc !== '') {
                $ov['description'] = $siteDesc;
            }
            try {
                $headHtml = (string) $seo->renderHead($content, $ov);
            } catch (\Throwable $e) {
                $headHtml = '';
            }
        }
        if ($headHtml === '') {
            $headHtml = '<title>' . htmlspecialchars($siteName) . '</title>'
                . ($siteDesc !== '' ? '<meta name="description" content="' . htmlspecialchars($siteDesc) . '">' : '');
        }

        $needFa = $needFa || ($content && strpos((string) ($content['body'] ?? ''), 'fa-') !== false);

        return [
            'theme' => $theme,
            'parts' => self::partsPath(),
            'site_name' => $siteName,
            'site_desc' => $siteDesc,
            'logo' => $logoUrl,
            'logo_dark' => $logoDark,
            'brand_mode' => $brandMode,
            'favicon' => $favicon !== '' ? $favicon : '/favicon.svg',
            'brand' => $brand,
            'nav' => $navDesktop,
            'drawer' => $navDrawer,
            'cta' => $cta,
            'chrome' => $useChrome,
            'canvas' => $canvas,
            'header_html' => $hdrHtml,
            'footer_html' => $ftrHtml,
            'footer_cols' => $cols,
            'footer_auto' => $footerAuto,
            'footer_text' => (string) mova_setting('footer_text', ''),
            'body_class' => implode(' ', array_unique($classes)),
            'mobile_icon' => $mobileIcon,
            'need_fa' => $needFa,
            'head_html' => $headHtml,
            'css' => (array) ($opts['css'] ?? []),
            'js' => (array) ($opts['js'] ?? []),
            'content' => $content,
            'view' => $view,
            'seo' => $seo,
            'ver' => self::assetVersion(),
            'search' => !isset($opts['search']) || $opts['search'],
        ];
    }

    /** Initial(s) for the default brand mark / favicon. */
    public static function initial(string $name): string
    {
        $name = trim($name) ?: 'M';
        return mb_strtoupper(mb_substr($name, 0, 1));
    }
}
