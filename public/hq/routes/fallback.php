<?php

declare(strict_types=1);


/** @var \Mova\Core\Router $router */

// Unknown HQ address: a real 404 with a way forward (search + overview), not a "coming soon" stub.
$router->get('/{any}', function () {
    requireAuth();
    return renderHq('placeholder', ['title' => 'Page not found'])->status(404);
});
