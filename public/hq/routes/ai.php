<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\AI\AiAssistService;
use Mova\Analysis\ContentAnalyzer;
use Mova\Content\ContentRepository;
use Mova\Content\RelationService;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

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
