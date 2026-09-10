<?php
declare(strict_types=1);

use Mova\Core\Response;
use Mova\Update\IdentityService;
use Mova\Update\UpdateService;

/** @var \Mova\Core\Router $router */

$router->get('/updates', function () {
    requireAuth();
    $identity = new IdentityService();
    $update = new UpdateService($identity);
    $check = $update->check();
    $meta = $identity->read();
    return renderHq('updates/index', [
        'title' => 'Updates',
        'check' => $check,
        'meta' => $meta,
    ]);
});

$router->post('/updates/identity', function () {
    requireAuth();
    $identity = new IdentityService();
    $name = trim((string) ($_POST['site_name'] ?? ''));
    $opts = ['touch_updated' => true];
    if ($name !== '') {
        $opts['name'] = $name;
    }
    $identity->refresh($opts);
    flash('success', 'Identity files refreshed (mova.json, mova.txt).');
    return Response::redirect('/hq/updates');
});
