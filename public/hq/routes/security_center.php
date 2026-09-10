<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

$router->get('/security', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $user = Auth::user();
    $logins = Database::fetchAll(
        "SELECT * FROM login_history ORDER BY created_at DESC LIMIT 30"
    );
    $devices = [];
    try {
        $devices = Database::fetchAll(
            "SELECT * FROM trusted_devices WHERE user_id = :u ORDER BY last_seen_at DESC",
            ['u' => Auth::id()]
        );
    } catch (\Throwable $e) {
    }
    $suppressions = [];
    try {
        $suppressions = Database::fetchAll("SELECT * FROM mail_suppressions ORDER BY created_at DESC LIMIT 50");
    } catch (\Throwable $e) {
    }

    return renderHq('security/index', [
        'user' => $user,
        'logins' => $logins,
        'devices' => $devices,
        'suppressions' => $suppressions,
        'title' => 'Security center',
    ]);
});

$router->post('/security', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $action = (string) $req->post('action', '');

    if ($action === 'suppress' && Auth::hasRole('owner', 'administrator')) {
        $email = strtolower(trim((string) $req->post('email', '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                Database::insert('mail_suppressions', [
                    'email' => $email,
                    'reason' => trim((string) $req->post('reason', 'manual')),
                    'created_at' => date('c'),
                ]);
            } catch (\Throwable $e) {
                // unique violation ok
            }
        }
        return (new Response())->redirect('/hq/security');
    }

    if ($action === 'unsuppress' && Auth::hasRole('owner', 'administrator')) {
        Database::delete('mail_suppressions', 'id = :id', ['id' => (int) $req->post('id', 0)]);
        return (new Response())->redirect('/hq/security');
    }

    // Basic TOTP enable flag (secret generation simplified for V1 of 2FA UI)
    if ($action === 'enable_2fa') {
        $secret = bin2hex(random_bytes(10));
        Database::update('users', [
            'totp_secret' => $secret,
            'totp_enabled' => 1,
            'updated_at' => date('c'),
        ], 'id = :id', ['id' => Auth::id()]);
        $_SESSION['_mova_totp_secret_once'] = $secret;
        return (new Response())->redirect('/hq/security?2fa=1');
    }

    if ($action === 'disable_2fa') {
        Database::update('users', [
            'totp_secret' => null,
            'totp_enabled' => 0,
            'updated_at' => date('c'),
        ], 'id = :id', ['id' => Auth::id()]);
        return (new Response())->redirect('/hq/security');
    }

    return (new Response())->redirect('/hq/security');
});
