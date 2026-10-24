<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Site\Pwa;

/** @var \Mova\Core\Router $router */

$router->get('/pwa', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    Pwa::reset();
    return renderHq('pwa/index', [
        'title' => 'App (PWA)',
        'raw' => Pwa::raw(),
        'cfg' => Pwa::config(),
        'defaults' => Pwa::defaults(),
        'gd' => Pwa::gdAvailable(),
        'https' => Pwa::isHttps(),
        'secure' => Pwa::isSecureContext(),
    ]);
});

$router->post('/pwa', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $str = static fn(string $k, int $max = 200): string => mb_substr(trim(strip_tags((string) $req->post($k, ''))), 0, $max);
    $hex = static function (string $k) use ($req): string {
        $v = trim((string) $req->post($k, ''));
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) ? strtolower($v) : '';
    };
    $num = static function (string $k, int $min, int $max, int $def) use ($req): string {
        $v = $req->post($k);
        if ($v === null || $v === '' || !is_numeric($v)) {
            return (string) $def;
        }
        return (string) max($min, min($max, (int) $v));
    };
    $display = (string) $req->post('pwa_display', 'standalone');
    $trigger = (string) $req->post('pwa_banner_trigger', 'delay');
    $icon = $str('pwa_icon', 500);
    if ($icon !== '' && !preg_match('~^(/|https?://)~i', $icon)) {
        $icon = '';
    }

    // Optional icon upload (png / jpg / webp / gif, ≤ 5 MB) → mova-uploads/pwa/
    $err = '';
    $f = $_FILES['pwa_icon_file'] ?? null;
    if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $info = ($f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) ? @getimagesize($f['tmp_name']) : false;
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime'] ?? ''] ?? null;
        if (!$info || !$ext || $f['size'] > 5 * 1024 * 1024) {
            $err = 'icon';
        } else {
            $target = dirname(__DIR__, 2) . '/mova-uploads';
            @mkdir($target . '/pwa', 0775, true);
            $name = 'app-icon-' . date('YmdHis') . '.' . $ext;
            if (@move_uploaded_file($f['tmp_name'], $target . '/pwa/' . $name)) {
                $icon = '/mova-uploads/pwa/' . $name;
            } else {
                $err = 'icon';
            }
        }
    }

    Pwa::save([
        'pwa_enabled' => $req->post('pwa_enabled') ? '1' : '0',
        'pwa_name' => $str('pwa_name', 60),
        'pwa_short_name' => $str('pwa_short_name', 24),
        'pwa_theme_color' => $hex('pwa_theme_color'),
        'pwa_bg_color' => $hex('pwa_bg_color'),
        'pwa_icon' => $icon,
        'pwa_display' => in_array($display, Pwa::DISPLAYS, true) ? $display : 'standalone',
        'pwa_start_url' => Pwa::cleanStartUrl($str('pwa_start_url', 300)),
        'pwa_banner' => $req->post('pwa_banner') ? '1' : '0',
        'pwa_banner_msg' => $str('pwa_banner_msg', 160),
        'pwa_banner_trigger' => in_array($trigger, ['delay', 'pageviews'], true) ? $trigger : 'delay',
        'pwa_banner_delay' => $num('pwa_banner_delay', 0, 600, 8),
        'pwa_banner_pageviews' => $num('pwa_banner_pageviews', 1, 50, 2),
        'pwa_banner_snooze_days' => $num('pwa_banner_snooze_days', 0, 365, 14),
        'pwa_offline' => $req->post('pwa_offline') ? '1' : '0',
    ]);
    Audit::log('pwa.updated', 'settings', null, ['enabled' => (bool) $req->post('pwa_enabled')]);
    return (new Response())->redirect('/hq/pwa?saved=1' . ($err ? '&err=' . $err : ''));
});
