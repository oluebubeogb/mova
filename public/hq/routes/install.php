<?php

declare(strict_types=1);
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Core\Schema;
use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Core\Requirements;
use Mova\Core\PlaceholderCleanup;

/** @var \Mova\Core\Router $router */

$router->get('/install', function (Request $req) {
    if (Schema::isInstalled() && Schema::hasOwner()) {
        return (new Response())->redirect('/hq');
    }
    $placeholders = PlaceholderCleanup::neutralize();
    $requirements = Requirements::summary();
    return renderHq('install', [
        'step' => Schema::isInstalled() ? 'owner' : 'schema',
        'requirements' => $requirements,
        'placeholders_moved' => $placeholders,
    ]);
});

$router->post('/install', function (Request $req) {
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Invalid CSRF token');
    }

    PlaceholderCleanup::neutralize();

    $requirements = Requirements::summary();
    $force = (string) $req->post('force_install', '') === '1';

    // Only hard-stop if storage is not writable (SQLite cannot install)
    if (!$requirements['disk_ok'] && !$force) {
        return renderHq('install', [
            'step' => Schema::isInstalled() ? 'owner' : 'schema',
            'requirements' => $requirements,
            'error' => 'storage/ must be writable to create the database. Fix folder permissions, or check “Proceed anyway” if you understand the risk.',
            'allow_force' => true,
        ]);
    }

    try {
        if (!Schema::isInstalled()) {
            Schema::install();
        }
    } catch (\Throwable $e) {
        return renderHq('install', [
            'step' => 'schema',
            'requirements' => Requirements::summary(),
            'error' => 'Could not create the database: ' . $e->getMessage()
                . ' — usually means storage/ is not writable or pdo_sqlite is missing.',
            'allow_force' => true,
        ]);
    }

    if (!Schema::hasOwner()) {
        $name = trim((string) $req->post('name', ''));
        $username = trim((string) $req->post('username', ''));
        $email = trim((string) $req->post('email', ''));
        $password = (string) $req->post('password', '');

        // Schema-only step (no owner form fields yet)
        if ($name === '' && $username === '' && $email === '' && $password === '') {
            return renderHq('install', [
                'step' => 'owner',
                'requirements' => Requirements::summary(),
                'notice' => !$requirements['php_ok']
                    ? 'Installed with some PHP items missing. Some features may not work until your host enables them.'
                    : null,
            ]);
        }

        if (!$name || !$username || !$email || strlen($password) < 8) {
            return renderHq('install', [
                'step' => 'owner',
                'error' => 'Please fill all fields. Password must be at least 8 characters.',
                'requirements' => Requirements::summary(),
            ]);
        }

        Database::insert('users', [
            'name'       => $name,
            'username'   => $username,
            'email'      => $email,
            'password'   => Auth::hashPassword($password),
            'role'       => 'owner',
            'status'     => 'active',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);

        $defaults = [
            'site_name'        => 'Mova',
            'site_description' => 'Content that moves.',
            'comments_enabled' => '0',
        ];
        foreach ($defaults as $k => $v) {
            Database::insert('settings', [
                'setting_key'   => $k,
                'setting_value' => $v,
                'updated_at'    => date('c'),
            ]);
        }
    }

    return (new Response())->redirect('/hq/login');
});
