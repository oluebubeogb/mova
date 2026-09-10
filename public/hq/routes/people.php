<?php

declare(strict_types=1);
use Mova\Core\Bootstrap;
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

$router->get('/people', function () {
    requireAuth();
    if (!Auth::can('manage_users') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $users = Database::fetchAll("SELECT id, name, username, email, role, status, last_login_at, created_at FROM users ORDER BY created_at");
    $roles = Bootstrap::config('roles', []);
    return renderHq('people/list', compact('users', 'roles'));
});

$router->get('/people/new', function () {
    requireAuth();
    if (!Auth::can('manage_users') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $roles = Bootstrap::config('roles', []);
    return renderHq('people/edit', ['user' => null, 'roles' => $roles]);
});

$router->post('/people/new', function (Request $req) {
    requireAuth();
    if (!Auth::can('manage_users') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $name = trim((string) $req->post('name', ''));
    $username = trim((string) $req->post('username', ''));
    $email = trim((string) $req->post('email', ''));
    $password = (string) $req->post('password', '');
    $role = (string) $req->post('role', 'author');
    $status = (string) $req->post('status', 'active');

    $roles = Bootstrap::config('roles', []);
    if (!$name || !$username || !$email || strlen($password) < 8) {
        return renderHq('people/edit', [
            'user' => null,
            'roles' => $roles,
            'error' => 'All fields required. Password must be at least 8 characters.',
        ]);
    }
    if (!isset($roles[$role]) || $role === 'owner') {
        $role = 'author';
    }
    // Only owner can create administrators
    if ($role === 'administrator' && !Auth::hasRole('owner')) {
        $role = 'editor';
    }

    if (Database::exists('users', 'username = :u OR email = :e', ['u' => $username, 'e' => $email])) {
        return renderHq('people/edit', [
            'user' => null,
            'roles' => $roles,
            'error' => 'Username or email already in use.',
        ]);
    }

    Database::insert('users', [
        'name'       => $name,
        'username'   => $username,
        'email'      => $email,
        'password'   => Auth::hashPassword($password),
        'role'       => $role,
        'status'     => $status,
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ]);

    return (new Response())->redirect('/hq/people');
});

$router->get('/people/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Auth::can('manage_users') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $user = Database::fetch(
        "SELECT id, name, username, email, role, status, created_at FROM users WHERE id = :id",
        ['id' => (int) $params['id']]
    );
    if (!$user) {
        return (new Response())->redirect('/hq/people');
    }
    $roles = Bootstrap::config('roles', []);
    return renderHq('people/edit', compact('user', 'roles'));
});

$router->post('/people/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Auth::can('manage_users') && !Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $id = (int) $params['id'];
    $user = Database::fetch("SELECT * FROM users WHERE id = :id", ['id' => $id]);
    if (!$user) {
        return (new Response())->redirect('/hq/people');
    }
    $roles = Bootstrap::config('roles', []);
    $name = trim((string) $req->post('name', ''));
    $username = trim((string) $req->post('username', ''));
    $email = trim((string) $req->post('email', ''));
    $role = (string) $req->post('role', $user['role']);
    $status = (string) $req->post('status', 'active');
    $password = (string) $req->post('password', '');

    // Protect owner account
    if ($user['role'] === 'owner') {
        $role = 'owner';
        $status = 'active';
        if ($id !== Auth::id() && !Auth::hasRole('owner')) {
            return (new Response())->status(403)->body('Forbidden');
        }
    } else {
        if (!isset($roles[$role]) || $role === 'owner') {
            $role = $user['role'];
        }
        if ($role === 'administrator' && !Auth::hasRole('owner')) {
            $role = $user['role'];
        }
    }

    $update = [
        'name'       => $name,
        'username'   => $username,
        'email'      => $email,
        'role'       => $role,
        'status'     => $status,
        'updated_at' => date('c'),
    ];
    if ($password !== '' && strlen($password) >= 8) {
        $update['password'] = Auth::hashPassword($password);
    }

    Database::update('users', $update, 'id = :id', ['id' => $id]);
    return (new Response())->redirect('/hq/people?saved=1');
});
