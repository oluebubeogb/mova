<?php

declare(strict_types=1);
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

$router->get('/login', function (Request $req) {
    if (Auth::check()) {
        return (new Response())->redirect(Auth::pullIntendedUrl('/hq'));
    }
    $data = [];
    if ($req->query('timeout') || Auth::wasTimedOut()) {
        $data['error'] = 'Your session expired due to inactivity. Please sign in again to continue where you left off.';
        Auth::clearTimedOutFlag();
    }
    return renderHq('login', $data);
});

$router->post('/login', function (Request $req) {
    if (!Csrf::validate()) {
        return renderHq('login', ['error' => 'Invalid security token. Please try again.']);
    }

    $username = trim((string) $req->post('username', ''));
    $password = (string) $req->post('password', '');

    if (Auth::attempt($username, $password)) {
        $dest = Auth::pullIntendedUrl('/hq');
        return (new Response())->redirect($dest);
    }

    return renderHq('login', ['error' => 'Invalid credentials or too many attempts.']);
});

$router->get('/logout', function () {
    Auth::logout();
    return (new Response())->redirect('/hq/login');
});
