<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Site\SiteService;
use Mova\Theme\ThemeManager;

/** @var \Mova\Core\Router $router */

$router->get('/sites', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new SiteService();
    $themes = (new ThemeManager())->all();
    return renderHq('sites/index', [
        'sites' => $svc->all(),
        'themes' => $themes,
        'title' => 'Sites',
    ]);
});

$router->post('/sites', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new SiteService();
    $action = (string) $req->post('action', 'create');

    if ($action === 'delete') {
        $svc->delete((int) $req->post('id', 0));
        Audit::log('site.deleted', 'site', (int) $req->post('id', 0));
        return (new Response())->redirect('/hq/sites');
    }
    if ($action === 'primary') {
        $svc->setPrimary((int) $req->post('id', 0));
        return (new Response())->redirect('/hq/sites');
    }
    if ($action === 'update') {
        $svc->update((int) $req->post('id', 0), [
            'name' => $req->post('name', ''),
            'domain' => $req->post('domain', ''),
            'theme' => $req->post('theme', 'default'),
            'status' => $req->post('status', 'active'),
        ]);
        return (new Response())->redirect('/hq/sites?saved=1');
    }

    $id = $svc->create([
        'name' => $req->post('name', ''),
        'slug' => $req->post('slug', ''),
        'domain' => $req->post('domain', ''),
        'theme' => $req->post('theme', 'default'),
    ]);
    Audit::log('site.created', 'site', $id);
    return (new Response())->redirect('/hq/sites');
});
