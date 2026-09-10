<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Cache\PageCache;
use Mova\Theme\ThemeManager;
use Mova\Theme\DesignConfig;

/** @var \Mova\Core\Router $router */

function mova_design_guard(): ?Response
{
    requireAuth();
    if (!Auth::can('manage_appearance') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    return null;
}

// ---- Brand ----
$router->get('/brand', function () {
    if ($r = mova_design_guard()) {
        return $r;
    }
    // Ensure default header/footer assemblies exist for suggestions
    if (class_exists(\Mova\Assembly\AssemblyService::class)) {
        $asm = new \Mova\Assembly\AssemblyService();
        $asm->ensureTable();
        if (!$asm->findBySlug('site-header')) {
            $asm->create([
                'name' => 'Site header',
                'slug' => 'site-header',
                'description' => 'Default header assembly',
                'html' => '<header class="asm-site-header" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.75rem 0;border-bottom:1px solid #e5e7eb;"><a href="/" style="font-weight:700;text-decoration:none;color:inherit;">{{site_name}}</a><nav style="display:flex;gap:1rem;flex-wrap:wrap;"><a href="/">Home</a><a href="/search">Search</a></nav></header>',
                'css' => '.asm-site-header a{color:inherit;}',
                'status' => 'published',
            ]);
        }
        if (!$asm->findBySlug('site-footer')) {
            $asm->create([
                'name' => 'Site footer',
                'slug' => 'site-footer',
                'description' => 'Default footer assembly',
                'html' => '<footer class="asm-site-footer" style="padding:1.25rem 0;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;font-size:0.9rem;opacity:0.85;"><span>&copy; {{year}} {{site_name}}</span><span><a href="/feed.xml">Feed</a> · <a href="/llms.txt">llms.txt</a></span></footer>',
                'css' => '.asm-site-footer a{color:inherit;}',
                'status' => 'published',
            ]);
        }
    }
    $settings = [
        'site_name' => DesignConfig::setting('site_name', 'Mova'),
        'site_description' => DesignConfig::setting('site_description', ''),
        'logo_url' => DesignConfig::setting('logo_url', ''),
        'favicon_url' => DesignConfig::setting('favicon_url', ''),
        'footer_text' => DesignConfig::setting('footer_text', ''),
        'brand_color' => DesignConfig::setting('brand_color', '#2563eb'),
        'homepage_content_id' => DesignConfig::setting('homepage_content_id', ''),
        'header_assembly_slug' => DesignConfig::setting('header_assembly_slug', ''),
        'footer_assembly_slug' => DesignConfig::setting('footer_assembly_slug', ''),
    ];
    $hid = (int) ($settings['homepage_content_id'] ?? 0);
    if ($hid > 0) {
        try {
            $row = \Mova\Core\Database::fetch('SELECT title, slug FROM content WHERE id = :id', ['id' => $hid]);
            if ($row) {
                $settings['homepage_label'] = $row['title'] . ' (/' . $row['slug'] . ')';
            }
        } catch (\Throwable $e) {}
    }
    $themes = (new ThemeManager())->all();
    $activeTheme = (new ThemeManager())->activeSlug();
    return renderHq('design/brand', [
        'settings' => $settings,
        'themes' => $themes,
        'activeTheme' => $activeTheme,
        'title' => 'Brand',
    ]);
});

$router->get('/brand/suggest-content', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    $q = trim((string) $req->query('q', ''));
    try {
        if ($q !== '') {
            $rows = \Mova\Core\Database::fetchAll(
                "SELECT id, title, slug FROM content WHERE status = 'published' AND (title LIKE :q OR slug LIKE :q2) ORDER BY updated_at DESC LIMIT 12",
                ['q' => '%' . $q . '%', 'q2' => '%' . $q . '%']
            );
        } else {
            $rows = \Mova\Core\Database::fetchAll(
                "SELECT id, title, slug FROM content WHERE status = 'published' ORDER BY updated_at DESC LIMIT 12"
            );
        }
    } catch (\Throwable $e) {
        $rows = [];
    }
    return (new Response())->header('Content-Type', 'application/json')->body(json_encode(['items' => $rows]));
});

$router->get('/brand/suggest-assembly', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    $q = trim((string) $req->query('q', ''));
    $items = [];
    if (class_exists(\Mova\Assembly\AssemblyService::class)) {
        $svc = new \Mova\Assembly\AssemblyService();
        foreach ($svc->published($q) as $it) {
            $items[] = ['id' => (int) $it['id'], 'name' => $it['name'], 'slug' => $it['slug']];
        }
    }
    return (new Response())->header('Content-Type', 'application/json')->body(json_encode(['items' => $items]));
});

$router->post('/brand', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    foreach (['site_name', 'site_description', 'logo_url', 'favicon_url', 'footer_text', 'brand_color', 'homepage_content_id', 'header_assembly_slug', 'footer_assembly_slug'] as $key) {
        DesignConfig::saveSetting($key, (string) $req->post($key, ''));
    }
    // Keep brand_color in sync with primary token for compatibility
    $primary = (string) $req->post('brand_color', '#2563eb');
    $tokens = DesignConfig::tokens();
    $tokens['colors']['primary'] = $primary;
    DesignConfig::saveTokens($tokens);

    $theme = (string) $req->post('active_theme', 'default');
    (new ThemeManager())->setActive($theme);

    PageCache::flush();
    Audit::log('design.brand.updated');
    return (new Response())->redirect('/hq/brand?saved=1');
});

// ---- Style (design tokens) ----
$router->get('/style', function () {
    if ($r = mova_design_guard()) {
        return $r;
    }
    return renderHq('design/style', [
        'tokens' => DesignConfig::tokens(),
        'title' => 'Style',
    ]);
});

$router->post('/style', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $tokens = DesignConfig::tokens();
    $colorKeys = ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'border'];
    foreach ($colorKeys as $ck) {
        $tokens['colors'][$ck] = (string) $req->post('color_' . $ck, $tokens['colors'][$ck] ?? '');
        $tokens['colors_dark'][$ck] = (string) $req->post('color_dark_' . $ck, $tokens['colors_dark'][$ck] ?? '');
    }
    $tokens['typography']['font_sans'] = (string) $req->post('font_sans', $tokens['typography']['font_sans'] ?? '');
    $tokens['typography']['scale'] = (string) $req->post('font_scale', '1');
    $tokens['radius']['sm'] = (string) $req->post('radius_sm', '6px');
    $tokens['radius']['md'] = (string) $req->post('radius_md', '10px');
    $tokens['radius']['lg'] = (string) $req->post('radius_lg', '16px');
    $tokens['shadows']['style'] = (string) $req->post('shadow_style', 'soft');
    if ($tokens['shadows']['style'] === 'none') {
        $tokens['shadows']['sm'] = 'none';
        $tokens['shadows']['md'] = 'none';
    } elseif ($tokens['shadows']['style'] === 'medium') {
        $tokens['shadows']['sm'] = '0 2px 4px rgba(0,0,0,0.08)';
        $tokens['shadows']['md'] = '0 8px 24px rgba(0,0,0,0.12)';
    } elseif ($tokens['shadows']['style'] === 'sharp') {
        $tokens['shadows']['sm'] = '0 1px 0 rgba(0,0,0,0.1)';
        $tokens['shadows']['md'] = '0 4px 0 rgba(0,0,0,0.08)';
    } else {
        $tokens['shadows']['sm'] = '0 1px 2px rgba(0,0,0,0.04)';
        $tokens['shadows']['md'] = '0 4px 12px rgba(0,0,0,0.08)';
    }
    $tokens['spacing']['density'] = (string) $req->post('density', 'normal');
    if ($tokens['spacing']['density'] === 'compact') {
        $tokens['spacing']['section'] = '1.5rem';
        $tokens['spacing']['element'] = '1rem';
    } elseif ($tokens['spacing']['density'] === 'spacious') {
        $tokens['spacing']['section'] = '4rem';
        $tokens['spacing']['element'] = '2rem';
    } else {
        $tokens['spacing']['section'] = '2.5rem';
        $tokens['spacing']['element'] = '1.5rem';
    }
    DesignConfig::saveTokens($tokens);
    // Sync brand_color for older consumers
    DesignConfig::saveSetting('brand_color', $tokens['colors']['primary'] ?? '#2563eb');
    PageCache::flush();
    Audit::log('design.style.updated');
    return (new Response())->redirect('/hq/style?saved=1');
});

// ---- Layout ----
$router->get('/layout', function () {
    if ($r = mova_design_guard()) {
        return $r;
    }
    return renderHq('design/layout', [
        'layout' => DesignConfig::layout(),
        'nav_links' => DesignConfig::setting('nav_links', "Home|/\nSearch|/search"),
        'mobile_menu_icon_mode' => DesignConfig::setting('mobile_menu_icon_mode', 'preset'),
        'mobile_menu_icon_preset' => DesignConfig::setting('mobile_menu_icon_preset', 'fa-bars'),
        'mobile_menu_icon_custom' => DesignConfig::setting('mobile_menu_icon_custom', ''),
        'mobile_menu_icon_url' => DesignConfig::setting('mobile_menu_icon_url', ''),
        'mobileIconPresets' => [
            'fa-bars' => 'Bars',
            'fa-bars-staggered' => 'Staggered',
            'fa-ellipsis-vertical' => 'Ellipsis',
            'fa-grip-lines' => 'Grip',
            'fa-align-justify' => 'Justify',
        ],
        'title' => 'Layout',
    ]);
});

$router->post('/layout', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $layout = DesignConfig::layout();
    $layout['container_width'] = (string) $req->post('container_width', '720px');
    $layout['header']['type'] = (string) $req->post('header_type', 'sticky');
    $layout['header']['logo_position'] = (string) $req->post('logo_position', 'left');
    $layout['header']['nav_align'] = (string) $req->post('nav_align', 'right');
    $layout['header']['transparent'] = $req->post('header_transparent', '') === '1';
    $layout['footer']['style'] = (string) $req->post('footer_style', 'simple');
    $layout['mobile_nav']['style'] = (string) $req->post('mobile_nav_style', 'drawer-right');
    $align = (string) $req->post('mobile_nav_content_align', 'left');
    if (!in_array($align, ['left', 'center', 'right'], true)) {
        $align = 'left';
    }
    $layout['mobile_nav']['content_align'] = $align;
    DesignConfig::saveLayout($layout);

    DesignConfig::saveSetting('nav_links', (string) $req->post('nav_links', ''));

    $mode = (string) $req->post('mobile_menu_icon_mode', 'preset');
    if (!in_array($mode, ['preset', 'custom', 'upload'], true)) {
        $mode = 'preset';
    }
    DesignConfig::saveSetting('mobile_menu_icon_mode', $mode);
    $preset = (string) $req->post('mobile_menu_icon_preset', 'fa-bars');
    $allowed = ['fa-bars', 'fa-bars-staggered', 'fa-ellipsis-vertical', 'fa-grip-lines', 'fa-align-justify'];
    if (!in_array($preset, $allowed, true)) {
        $preset = 'fa-bars';
    }
    DesignConfig::saveSetting('mobile_menu_icon_preset', $preset);
    $custom = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', (string) $req->post('mobile_menu_icon_custom', '')) ?? '';
    DesignConfig::saveSetting('mobile_menu_icon_custom', trim(preg_replace('/\s+/', ' ', $custom) ?? ''));
    DesignConfig::saveSetting('mobile_menu_icon_url', (string) $req->post('mobile_menu_icon_url', ''));

    PageCache::flush();
    Audit::log('design.layout.updated');
    return (new Response())->redirect('/hq/layout?saved=1');
});

// ---- Components ----
$router->get('/components', function () {
    if ($r = mova_design_guard()) {
        return $r;
    }
    return renderHq('design/components', [
        'components' => DesignConfig::components(),
        'title' => 'Components',
    ]);
});

$router->post('/components', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $c = DesignConfig::components();
    $c['button']['variant'] = (string) $req->post('button_variant', 'solid');
    $c['button']['radius'] = (string) $req->post('button_radius', 'md');
    $c['button']['uppercase'] = $req->post('button_uppercase', '') === '1';
    $c['card']['variant'] = (string) $req->post('card_variant', 'elevated');
    $c['card']['radius'] = (string) $req->post('card_radius', 'md');
    $c['card']['hover'] = (string) $req->post('card_hover', 'lift');
    $c['hero']['variant'] = (string) $req->post('hero_variant', 'centered');
    DesignConfig::saveComponents($c);
    PageCache::flush();
    Audit::log('design.components.updated');
    return (new Response())->redirect('/hq/components?saved=1');
});

// Redirect legacy /appearance → /brand
$router->get('/appearance', function () {
    return (new Response())->redirect('/hq/brand');
});


// ---- Elements (Phase 1) ----
$router->get('/elements', function () {
    if ($r = mova_design_guard()) {
        return $r;
    }
    $styles = \Mova\Theme\ElementStyles::getAll();
    $styledTags = array_keys($styles);
    return renderHq('design/elements', [
        'topTags' => \Mova\Theme\ElementStyles::topTags(),
        'allTags' => \Mova\Theme\ElementStyles::allTags(),
        'styles' => $styles,
        'styledTags' => $styledTags,
        'title' => 'Elements',
    ]);
});


$router->post('/elements', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $tag = \Mova\Theme\ElementStyles::normalizeTag((string) $req->post('tag', ''));
    if ($tag === '') {
        return (new Response())->redirect('/hq/elements?error=tag');
    }
    $entryRaw = (string) $req->post('entry_json', '');
    $entry = json_decode($entryRaw, true);
    if (!is_array($entry)) {
        $bpRaw = (string) $req->post('breakpoints_json', '');
        $entry = json_decode($bpRaw, true);
    }
    if (!is_array($entry)) {
        $entry = [
            'desktop' => [
                'props' => json_decode((string) $req->post('props_json', '{}'), true) ?: [],
                'custom_css' => (string) $req->post('custom_css', ''),
            ],
        ];
    }
    \Mova\Theme\ElementStyles::saveTagEntry($tag, $entry);
    PageCache::flush();
    Audit::log('design.elements.updated', 'element', 0);
    return (new Response())->redirect('/hq/elements?saved=1&tag=' . rawurlencode($tag));
});

$router->get('/elements/data', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    $tag = \Mova\Theme\ElementStyles::normalizeTag((string) $req->query('tag', ''));
    $data = $tag !== '' ? \Mova\Theme\ElementStyles::getTag($tag) : \Mova\Theme\ElementStyles::emptyEntry();
    return (new Response())->json(['tag' => $tag, 'data' => $data]);
});

$router->get('/elements/export', function () {
    if ($r = mova_design_guard()) {
        return $r;
    }
    $all = \Mova\Theme\ElementStyles::getAll();
    $body = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return (new Response())
        ->header('Content-Type', 'application/json; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="mova-element-styles.json"')
        ->body($body ?: '{}');
});

$router->post('/elements/import', function (Request $req) {
    if ($r = mova_design_guard()) {
        return $r;
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $raw = (string) $req->post('import_json', '');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return (new Response())->redirect('/hq/elements?error=import');
    }
    $merge = $req->post('import_merge', '1') === '1';
    $count = \Mova\Theme\ElementStyles::importAll($data, $merge);
    PageCache::flush();
    Audit::log('design.elements.imported', 'element', 0, ['count' => $count]);
    return (new Response())->redirect('/hq/elements?imported=' . (int) $count);
});
