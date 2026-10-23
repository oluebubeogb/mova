<?php

declare(strict_types=1);
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;

/** @var \Mova\Core\Router $router */

$router->get('/settings', function () {
    requireAuth();
    if (!Auth::can('manage_settings') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $settings = [];
    $rows = Database::fetchAll("SELECT setting_key, setting_value FROM settings");
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    return renderHq('settings', compact('settings'));
});

$router->post('/settings', function (Request $req) {
    requireAuth();
    if (!Auth::can('manage_settings') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }

    $keys = [
        'site_name', 'site_description', 'comments_enabled',
        'ai_api_key', 'ai_api_url', 'ai_model', 'ai_provider',
        'ai_img_api_key', 'ai_img_api_url', 'ai_img_model', 'ai_img_provider',
        'ai_tool_mode', 'ai_worker_mode',
    ];
    $secrets = \Mova\AI\AiConfig::SECRET_KEYS;
    $upsert = static function (string $key, string $val): void {
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            ['k' => $key, 'v' => $val, 't' => date('c'), 'v2' => $val, 't2' => date('c')]
        );
    };
    foreach ($keys as $key) {
        if ($key === 'comments_enabled') {
            $upsert($key, $req->post('comments_enabled') ? '1' : '0');
            continue;
        }
        if (in_array($key, $secrets, true)) {
            // Secrets are never echoed back; blank means "keep", explicit checkbox clears.
            if ($req->post($key . '_clear')) {
                Database::query("DELETE FROM settings WHERE setting_key = :k", ['k' => $key]);
                continue;
            }
            $val = trim((string) $req->post($key, ''));
            if ($val !== '') {
                $upsert($key, $val);
            }
            continue;
        }
        // Only touch fields that were actually submitted (other screens own other keys).
        if ($req->post($key) === null) {
            continue;
        }
        $v = trim((string) $req->post($key, ''));
        if ($key === 'ai_tool_mode' && !in_array($v, ['auto', 'native', 'text'], true)) {
            $v = 'auto';
        }
        if ($key === 'ai_worker_mode' && !in_array($v, ['auto', 'worker', 'poke', 'request'], true)) {
            $v = 'auto';
        }
        $upsert($key, $v);
    }
    try {
        \Mova\AI\AiCapabilities::get(true); // URL may have changed → re-detect gateway features
    } catch (\Throwable $e) {
    }
    \Mova\AI\AiConfig::flush();
    try {
        \Mova\Cache\PageCache::flush(); // site name, nav, footer etc. appear on every cached page
    } catch (\Throwable $e) {
    }
    Audit::log('settings.updated');
    return (new Response())->redirect('/hq/settings?saved=1');
});

/**
 * Test connection for the AI provider form. Uses the values typed in the form; a blank key
 * means "the saved/effective key". The key itself is never returned or logged.
 */
$router->post('/settings/ai/test', function (Request $req) {
    requireAuth();
    if (!Auth::can('manage_settings') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->json(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    if (!Csrf::validate()) {
        return (new Response())->json(['ok' => false, 'error' => 'CSRF'], 403);
    }
    $url = trim((string) $req->post('ai_api_url', ''));
    $model = trim((string) $req->post('ai_model', ''));
    $key = trim((string) $req->post('ai_api_key', ''));
    $url = $url !== '' ? $url : \Mova\AI\AiConfig::get('ai_api_url');
    $model = $model !== '' ? $model : \Mova\AI\AiConfig::get('ai_model');
    $usedSaved = $key === '';
    if ($usedSaved) {
        $key = \Mova\AI\AiConfig::get('ai_api_key');
    }
    $withChat = (string) $req->post('chat', '0') === '1';
    $res = \Mova\AI\AiCapabilities::test($url, $key, $model, $withChat);
    $res['url'] = $url;
    $res['model'] = $model;
    $res['key'] = $key === '' ? 'none' : ($usedSaved ? 'saved key ' : 'typed key ') . \Mova\AI\AiConfig::mask($key);
    Audit::log('settings.ai_test', 'settings', null, ['ok' => (bool) $res['ok'], 'host' => parse_url($url, PHP_URL_HOST)]);
    return (new Response())->json($res);
});
