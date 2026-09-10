<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Integration\IntegrationManager;

/** @var \Mova\Core\Router $router */

$router->get('/integrations', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $mgr = new IntegrationManager();
    $all = $mgr->all();
    $provider = trim((string) ($_GET['provider'] ?? ''));
    $allowed = ['cdn', 'smtp', 'analytics', 'storage', 'ai'];
    if ($provider === '' || !in_array($provider, $allowed, true)) {
        $provider = 'cdn';
    }
    $filtered = array_values(array_filter($all, static function ($int) use ($provider) {
        return ($int['driver'] ?? '') === $provider;
    }));
    // Fallback if driver missing from registry
    if (!$filtered) {
        foreach ($all as $int) {
            if (($int['driver'] ?? '') === $provider) {
                $filtered[] = $int;
            }
        }
    }
    $titles = [
        'cdn' => 'CDN',
        'smtp' => 'Email API',
        'analytics' => 'Analytics',
        'storage' => 'Object storage',
        'ai' => 'AI provider',
    ];
    return renderHq('integrations/index', [
        'integrations' => $filtered,
        'provider' => $provider,
        'title' => $titles[$provider] ?? 'Integrations',
    ]);
});

$router->post('/integrations', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $driver = (string) $req->post('driver', '');
    $status = $req->post('enabled') ? 'enabled' : 'disabled';
    $config = $req->post('config', []);
    if (!is_array($config)) {
        $config = [];
    }
    (new IntegrationManager())->save($driver, $config, $status);
    Audit::log('integration.updated', 'integration', null, ['driver' => $driver]);
    return (new Response())->redirect('/hq/integrations?provider=' . urlencode((string) $req->post('return_provider', $req->post('driver', 'cdn'))) . '&saved=1');
});
