<?php

declare(strict_types=1);


/** @var \Mova\Core\Router $router */

$router->get('/{any}', function () {
    requireAuth();
    return renderHq('placeholder', ['title' => 'Coming soon']);
});
