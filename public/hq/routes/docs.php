<?php

declare(strict_types=1);

/** @var \Mova\Core\Router $router */

$router->get('/docs', function () {
    requireAuth();
    return renderHq('docs', ['title' => 'Documentation']);
});
