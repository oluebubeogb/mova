<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\AI\AiAssistService;
use Mova\AI\AiChatService;
use Mova\AI\HqMap;
use Mova\Analysis\ContentAnalyzer;
use Mova\Auth\Auth;
use Mova\Content\ContentRepository;
use Mova\Content\RelationService;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

// —— Mova AI panel (Phases 1–3): sessions + chat + navigate ——

$router->get('/ai/sessions', function () {
    requireAuth();
    $uid = (int) Auth::id();
    $svc = new AiChatService();
    return (new Response())->json(['ok' => true, 'sessions' => $svc->listSessions($uid)]);
});

$router->post('/ai/sessions', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->json(['error' => 'CSRF'], 403);
    }
    $uid = (int) Auth::id();
    $title = trim((string) $req->post('title', 'New chat'));
    $svc = new AiChatService();
    $session = $svc->createSession($uid, $title !== '' ? $title : 'New chat');
    return (new Response())->json(['ok' => true, 'session' => $session]);
});

$router->get('/ai/sessions/{id}', function (Request $req, array $params = []) {
    requireAuth();
    $uid = (int) Auth::id();
    $id = (int) ($params['id'] ?? 0);
    $svc = new AiChatService();
    $session = $svc->getSession($id, $uid);
    if (!$session) {
        return (new Response())->json(['error' => 'Not found'], 404);
    }
    $messages = $svc->listMessages($id, $uid);
    foreach ($messages as &$m) {
        $meta = $m['meta'] ?? null;
        $m['actions'] = [];
        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);
            if (is_array($decoded) && isset($decoded['actions'])) {
                $m['actions'] = $decoded['actions'];
            }
        }
    }
    unset($m);
    return (new Response())->json(['ok' => true, 'session' => $session, 'messages' => $messages]);
});

$router->post('/ai/chat', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->json(['error' => 'CSRF'], 403);
    }
    $uid = (int) Auth::id();
    $message = trim((string) $req->post('message', ''));
    $sessionId = (int) $req->post('session_id', 0) ?: null;
    $pageContext = [
        'route' => (string) $req->post('route', ''),
        'area' => (string) $req->post('area', ''),
        'layer' => (string) $req->post('layer', ''),
        'entityId' => (int) $req->post('entity_id', 0) ?: null,
    ];
    $svc = new AiChatService();
    $result = $svc->chat($uid, $sessionId, $message, $pageContext);
    $status = !empty($result['ok']) ? 200 : 400;
    return (new Response())->json($result, $status);
});

$router->get('/ai/map', function () {
    requireAuth();
    return (new Response())->json(['ok' => true, 'items' => HqMap::all()]);
});

$router->post('/ai/assist', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->json(['error' => 'CSRF'], 403);
    }
    $action = (string) $req->post('action', 'summarize');
    $allowed = ['outline', 'title', 'excerpt', 'meta', 'keywords', 'faq', 'summarize', 'improve'];
    if (!in_array($action, $allowed, true)) {
        return (new Response())->json(['error' => 'Unknown action'], 400);
    }

    $svc = new AiAssistService();
    $result = $svc->run($action, [
        'title'   => $req->post('title', ''),
        'body'    => $req->post('body', ''),
        'excerpt' => $req->post('excerpt', ''),
    ]);

    return (new Response())->json($result);
});

$router->post('/ai/analyze', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->json(['error' => 'CSRF'], 403);
    }
    $analyzer = new ContentAnalyzer();
    $report = $analyzer->analyze([
        'title'   => $req->post('title', ''),
        'body'    => $req->post('body', ''),
        'excerpt' => $req->post('excerpt', ''),
        'meta'    => [
            'seo_title'        => $req->post('seo_title', ''),
            'meta_description' => $req->post('meta_description', ''),
        ],
    ]);
    return (new Response())->json(['ok' => true, 'analysis' => $report]);
});

$router->get('/ai/link-suggestions', function (Request $req) {
    requireAuth();
    $q = trim((string) $req->query('q', ''));
    $exclude = (int) $req->query('exclude', 0);
    $repo = new ContentRepository();
    $items = $q !== ''
        ? $repo->search($q, 12)
        : $repo->all(['status' => 'published'], 12);

    $out = [];
    foreach ($items as $item) {
        if ($exclude && (int) $item['id'] === $exclude) {
            continue;
        }
        $out[] = [
            'id'    => (int) $item['id'],
            'title' => $item['title'],
            'slug'  => $item['slug'],
            'url'   => '/' . ltrim($item['slug'], '/'),
        ];
    }

    // Prefer already-related if exclude set
    if ($exclude) {
        $related = (new RelationService())->getRelated($exclude);
        foreach ($related as $r) {
            array_unshift($out, [
                'id'    => (int) $r['related_content_id'],
                'title' => $r['title'],
                'slug'  => $r['slug'],
                'url'   => '/' . ltrim($r['slug'], '/'),
                'related' => true,
            ]);
        }
        // unique by id
        $seen = [];
        $unique = [];
        foreach ($out as $row) {
            if (isset($seen[$row['id']])) {
                continue;
            }
            $seen[$row['id']] = true;
            $unique[] = $row;
        }
        $out = array_slice($unique, 0, 12);
    }

    return (new Response())->json(['ok' => true, 'items' => $out]);
});
