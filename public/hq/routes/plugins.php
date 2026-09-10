<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Plugin\PluginManager;

/** @var \Mova\Core\Router $router */

$router->get('/plugins', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $mgr = new PluginManager();
    return renderHq('plugins/index', [
        'plugins' => $mgr->discover(),
        'title' => 'Plugins',
    ]);
});

$router->post('/plugins', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $mgr = new PluginManager();
    $slug = (string) $req->post('slug', '');
    $action = (string) $req->post('action', 'activate');
    if ($action === 'activate') {
        $mgr->activate($slug);
        Audit::log('plugin.activated', 'plugin', null, ['slug' => $slug]);
    } else {
        $mgr->deactivate($slug);
        Audit::log('plugin.deactivated', 'plugin', null, ['slug' => $slug]);
    }
    return (new Response())->redirect('/hq/plugins');
});
