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

    $scopes = $req->post('scopes', []);
    if (!is_array($scopes)) {
        $scopes = [];
    }
    // Only issue known scopes (REST API v1). Legacy read/write remain valid for existing keys.
    $known = array_keys(\Mova\Api\RestApi::SCOPES);
    $scopes = array_values(array_unique(array_filter(array_map('strval', $scopes), static fn($s) => in_array($s, $known, true))));
    if (!$scopes) {
        $scopes = ['content:read'];
    }
    // content:write without content:read would be confusing — reading back what you wrote is implied.
    if (in_array('content:write', $scopes, true) && !in_array('content:read', $scopes, true)) {
        $scopes[] = 'content:read';
    }
    $expiresIn = (int) $req->post('expires_days', 0);
    $expiresAt = $expiresIn > 0 ? date('c', time() + min($expiresIn, 3650) * 86400) : null;
    $created = $svc->create(
        (string) $req->post('name', 'API Key'),
        $scopes,
        Auth::id(),
        $expiresAt
    );
    $_SESSION['_mova_new_api_key'] = $created['key'];
    Audit::log('api_key.created', 'api_key', $created['id']);
    return (new Response())->redirect('/hq/api-keys');
});
