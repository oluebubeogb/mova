<?php
declare(strict_types=1);
use Mova\Core\Response;

/** @var \Mova\Core\Router $router */

$router->get('/content-hub', function () {
    requireAuth();
    return renderHq('landings/content', ['title' => 'Content']);
});
$router->get('/audience', function () {
    requireAuth();
    return renderHq('landings/audience', ['title' => 'Audience']);
});
$router->get('/design', function () {
    requireAuth();
    return renderHq('landings/design', ['title' => 'Design']);
});
$router->get('/extend', function () {
    requireAuth();
    return renderHq('landings/extend', ['title' => 'Extend']);
});
$router->get('/operations', function () {
    requireAuth();
    return renderHq('landings/operations', ['title' => 'Operations']);
});
