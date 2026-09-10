<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Api\ApiKeyService;

/** @var \Mova\Core\Router $router */

$router->get('/api-keys', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new ApiKeyService();
    $keys = $svc->all();
    $newKey = $_SESSION['_mova_new_api_key'] ?? null;
    unset($_SESSION['_mova_new_api_key']);
    return renderHq('api/keys', [
        'keys' => $keys,
        'newKey' => $newKey,
        'title' => 'API keys',
    ]);
});

$router->post('/api-keys', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new ApiKeyService();
    $action = (string) $req->post('action', 'create');

    if ($action === 'revoke') {
        $svc->revoke((int) $req->post('id', 0));
        Audit::log('api_key.revoked', 'api_key', (int) $req->post('id', 0));
        return (new Response())->redirect('/hq/api-keys');
    }
    if ($action === 'delete') {
        $svc->delete((int) $req->post('id', 0));
        Audit::log('api_key.deleted', 'api_key', (int) $req->post('id', 0));
        return (new Response())->redirect('/hq/api-keys');
    }

    $scopes = $req->post('scopes', ['read']);
    if (!is_array($scopes)) {
        $scopes = ['read'];
    }
    $created = $svc->create(
        (string) $req->post('name', 'API Key'),
        $scopes,
        Auth::id()
    );
    $_SESSION['_mova_new_api_key'] = $created['key'];
    Audit::log('api_key.created', 'api_key', $created['id']);
    return (new Response())->redirect('/hq/api-keys');
});
