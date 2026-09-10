<?php

declare(strict_types=1);
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

$router->get('/login', function () {
    if (Auth::check()) {
        return (new Response())->redirect('/hq');
    }
    return renderHq('login');
});

$router->post('/login', function (Request $req) {
    if (!Csrf::validate()) {
        return renderHq('login', ['error' => 'Invalid security token. Please try again.']);
    }

    $username = trim((string) $req->post('username', ''));
    $password = (string) $req->post('password', '');

    if (Auth::attempt($username, $password)) {
        return (new Response())->redirect('/hq');
    }

    return renderHq('login', ['error' => 'Invalid credentials or too many attempts.']);
});

$router->get('/logout', function () {
    Auth::logout();
    return (new Response())->redirect('/hq/login');
});
