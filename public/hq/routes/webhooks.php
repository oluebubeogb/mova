<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Webhook\WebhookService;

/** @var \Mova\Core\Router $router */

$router->get('/webhooks', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new WebhookService();
    return renderHq('webhooks/index', [
        'webhooks' => $svc->all(),
        'events' => WebhookService::EVENTS,
        'title' => 'Webhooks',
    ]);
});

$router->post('/webhooks', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new WebhookService();
    $action = (string) $req->post('action', 'create');

    if ($action === 'delete') {
        $svc->delete((int) $req->post('id', 0));
        Audit::log('webhook.deleted', 'webhook', (int) $req->post('id', 0));
        return (new Response())->redirect('/hq/webhooks');
    }

    if ($action === 'toggle') {
        $wh = $svc->find((int) $req->post('id', 0));
        if ($wh) {
            $svc->update((int) $wh['id'], [
                'name' => $wh['name'],
                'url' => $wh['url'],
                'events' => $wh['events'],
                'status' => $wh['status'] === 'active' ? 'disabled' : 'active',
            ]);
        }
        return (new Response())->redirect('/hq/webhooks');
    }

    $events = $req->post('events', []);
    if (!is_array($events)) {
        $events = [];
    }
    $id = $svc->create([
        'name' => $req->post('name', 'Webhook'),
        'url' => $req->post('url', ''),
        'events' => $events,
    ]);
    Audit::log('webhook.created', 'webhook', $id);
    return (new Response())->redirect('/hq/webhooks');
});
