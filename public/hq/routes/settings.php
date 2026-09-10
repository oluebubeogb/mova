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
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }

    $keys = [
        'site_name', 'site_description', 'comments_enabled',
        
        
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password',
        'ai_api_key', 'ai_api_url', 'ai_model', 'ai_provider',
    ];
    foreach ($keys as $key) {
        if ($key === 'comments_enabled') {
            $val = $req->post('comments_enabled') ? '1' : '0';
        } else {
            $val = (string) $req->post($key, '');
        }
        Database::query(
            "INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :t)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v2, updated_at = :t2",
            ['k' => $key, 'v' => $val, 't' => date('c'), 'v2' => $val, 't2' => date('c')]
        );
    }
    Audit::log('settings.updated');
    return (new Response())->redirect('/hq/settings?saved=1');
});
