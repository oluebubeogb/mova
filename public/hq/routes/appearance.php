<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Cache\PageCache;
use Mova\Theme\ThemeManager;

/** @var \Mova\Core\Router $router */

$router->get('/appearance', function () {
    requireAuth();
    if (!Auth::can('manage_appearance') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $settings = [];
    $rows = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    $themeMgr = new ThemeManager();
    $themes = $themeMgr->all();
    $activeTheme = $themeMgr->activeSlug();

    $mobileIconPresets = [
        'fa-bars' => 'Bars (classic)',
        'fa-bars-staggered' => 'Bars staggered',
        'fa-ellipsis-vertical' => 'Vertical ellipsis',
        'fa-grip-lines' => 'Grip lines',
        'fa-align-justify' => 'Align justify',
    ];

    return renderHq('appearance/index', array_merge(
        compact('settings', 'themes', 'activeTheme', 'mobileIconPresets'),
        ['title' => 'Appearance']
    ));
});

$router->post('/appearance', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $keys = [
        'site_name',
        'site_description',
        'logo_url',
        'favicon_url',
        'brand_color',
        'footer_text',
        'nav_links',
        'mobile_menu_icon_mode',
        'mobile_menu_icon_preset',
        'mobile_menu_icon_custom',
        'mobile_menu_icon_url',
    ];
    foreach ($keys as $key) {
        $val = (string) $req->post($key, '');
        if ($key === 'mobile_menu_icon_mode') {
            $allowed = ['preset', 'custom', 'upload'];
            if (!in_array($val, $allowed, true)) {
                $val = 'preset';
            }
        }
        if ($key === 'mobile_menu_icon_preset') {
            $allowed = ['fa-bars', 'fa-bars-staggered', 'fa-ellipsis-vertical', 'fa-grip-lines', 'fa-align-justify'];
            if (!in_array($val, $allowed, true)) {
                $val = 'fa-bars';
            }
        }
        if ($key === 'mobile_menu_icon_custom') {
            $val = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $val) ?? '';
            $val = trim(preg_replace('/\s+/', ' ', $val) ?? '');
        }
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            ['k' => $key, 'v' => $val, 't' => date('c'), 'v2' => $val, 't2' => date('c')]
        );
    }
    $theme = (string) $req->post('active_theme', 'default');
    (new ThemeManager())->setActive($theme);

    PageCache::flush();
    Audit::log('appearance.updated');
    return (new Response())->redirect('/hq/appearance?saved=1');
});
