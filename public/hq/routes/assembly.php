<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Assembly\AssemblyService;
use Mova\Cache\PageCache;

/** @var \Mova\Core\Router $router */

$router->get('/assembly', function (Request $req) {
    requireAuth();
    $svc = new AssemblyService();
    $status = (string) $req->query('status', 'all');
    $q = trim((string) $req->query('q', ''));
    $items = $svc->all($status === 'all' ? null : $status, $q);
    foreach ($items as &$it) {
        $it['usage'] = $svc->usageCount((string) ($it['slug'] ?? ''));
    }
    unset($it);
    return renderHq('assembly/index', [
        'items' => $items,
        'status' => $status,
        'q' => $q,
        'title' => 'Assembly',
    ]);
});

$router->get('/assembly/new', function () {
    requireAuth();
    return renderHq('assembly/edit', [
        'item' => null,
        'title' => 'New assembly',
        'embed' => '',
    ]);
});

$router->get('/assembly/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    $id = (int) ($params['id'] ?? 0);
    $svc = new AssemblyService();
    $item = $svc->find($id);
    if (!$item) {
        return (new Response())->redirect('/hq/assembly');
    }
    return renderHq('assembly/edit', [
        'item' => $item,
        'title' => 'Edit: ' . ($item['name'] ?? 'Assembly'),
        'embed' => $svc->embedCode((string) $item['slug']),
        'usage' => $svc->usageCount((string) $item['slug']),
    ]);
});

$router->get('/assembly/picker', function (Request $req) {
    requireAuth();
    $svc = new AssemblyService();
    $q = trim((string) $req->query('q', ''));
    $items = $svc->published($q);
    $out = array_map(static function (array $it) use ($svc) {
        return [
            'id' => (int) $it['id'],
            'name' => $it['name'],
            'slug' => $it['slug'],
            'embed' => $svc->embedCode((string) $it['slug']),
            'updated_at' => $it['updated_at'] ?? '',
        ];
    }, $items);
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode(['ok' => true, 'items' => $out]));
});

$router->post('/assembly', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new AssemblyService();
    $user = Auth::user();
    $uid = $user ? (int) $user['id'] : null;
    $action = (string) $req->post('action', 'create');

    if ($action === 'delete') {
        $id = (int) $req->post('id', 0);
        $svc->delete($id);
        Audit::log('assembly.deleted', 'assembly', $id);
        PageCache::flush();
        return (new Response())->redirect('/hq/assembly');
    }

    if ($action === 'archive') {
        $id = (int) $req->post('id', 0);
        $svc->archive($id, $uid);
        Audit::log('assembly.archived', 'assembly', $id);
        PageCache::flush();
        return (new Response())->redirect('/hq/assembly');
    }

    if ($action === 'duplicate') {
        $id = (int) $req->post('id', 0);
        $newId = $svc->duplicate($id, $uid);
        Audit::log('assembly.duplicated', 'assembly', $id);
        PageCache::flush();
        if ($newId) {
            return (new Response())->redirect('/hq/assembly/edit/' . $newId);
        }
        return (new Response())->redirect('/hq/assembly');
    }

    $payload = [
        'name' => $req->post('name', 'Untitled assembly'),
        'slug' => $req->post('slug', ''),
        'description' => $req->post('description', ''),
        'html' => $req->post('html', ''),
        'css' => $req->post('css', ''),
        'css_global' => $req->post('css_global') ? 1 : 0,
    ];

    // Global CSS is advanced — keep for trusted editors

    $publish = ($action === 'publish');
    $id = (int) $req->post('id', 0);

    if ($id <= 0 || $action === 'create') {
        $payload['status'] = $publish ? 'published' : 'draft';
        $newId = $svc->create($payload, $uid);
        Audit::log('assembly.created', 'assembly', $newId);
        PageCache::flush();
        return (new Response())->redirect('/hq/assembly/edit/' . $newId);
    }

    // Save keeps existing status; Publish sets published
    if ($publish) {
        $payload['status'] = 'published';
    }
    $svc->update($id, $payload, $uid, $publish);
    Audit::log($publish ? 'assembly.published' : 'assembly.updated', 'assembly', $id);
    PageCache::flush();
    return (new Response())->redirect('/hq/assembly/edit/' . $id);
});

$router->post('/assembly/preview', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->header('Content-Type', 'application/json')->body('{"ok":false}');
    }
    $svc = new AssemblyService();
    $row = [
        'slug' => $svc->normalizeSlug((string) $req->post('slug', 'preview')),
        'html' => (string) $req->post('html', ''),
        'css' => (string) $req->post('css', ''),
        'css_global' => $req->post('css_global') ? 1 : 0,
        'revision' => 0,
    ];
    if ($row['slug'] === '') {
        $row['slug'] = 'preview';
    }
    $html = $svc->renderRow($row, true);
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode(['ok' => true, 'html' => $html]));
});
