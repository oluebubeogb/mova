<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

$router->get('/sequences', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $sequences = Database::fetchAll("SELECT * FROM mail_sequences ORDER BY created_at DESC");
    return renderHq('sequences/index', ['sequences' => $sequences, 'title' => 'Mail sequences']);
});

$router->post('/sequences', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $action = (string) $req->post('action', 'create');
    if ($action === 'delete') {
        Database::delete('mail_sequences', 'id = :id', ['id' => (int) $req->post('id', 0)]);
        return (new Response())->redirect('/hq/sequences');
    }
    $now = date('c');
    $id = Database::insert('mail_sequences', [
        'name' => trim((string) $req->post('name', 'Sequence')),
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $delay = (int) $req->post('delay_days', 0);
    $subject = trim((string) $req->post('subject', 'Welcome'));
    $body = (string) $req->post('body', '');
    Database::insert('mail_sequence_steps', [
        'sequence_id' => $id,
        'delay_days' => $delay,
        'subject' => $subject,
        'body' => $body,
        'sort_order' => 0,
    ]);
    return (new Response())->redirect('/hq/sequences');
});
