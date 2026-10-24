<?php

declare(strict_types=1);

use Mova\Auth\Auth;
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Hq\SearchIndex;

/** @var \Mova\Core\Router $router */

/**
 * GET /hq/search?q=… → grouped, role-filtered, highlighted results (JSON).
 * Titles/snippets are HTML-escaped server-side; only <mark> is added.
 */
$router->get('/search', function (Request $req) {
    requireAuth();
    $q = (string) $req->query('q', '');
    $limit = max(5, min(50, (int) $req->query('limit', 24)));
    try {
        $result = SearchIndex::search($q, $limit);
    } catch (\Throwable $e) {
        \Mova\Core\ErrorHandler::report('hq.search.route', $e);
        $result = ['q' => $q, 'engine' => 'error', 'took_ms' => 0, 'total' => 0, 'groups' => []];
    }
    return (new Response())
        ->header('Cache-Control', 'no-store')
        ->json($result);
});

/** POST /hq/search/rebuild → full rebuild (owner/admin). */
$router->post('/search/rebuild', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return hqForbidden('Only owners and administrators can rebuild the search index.');
    }
    if (!\Mova\Security\Csrf::validate()) {
        return (new Response())->json(['ok' => false, 'error' => 'Session expired — reload and try again.'], 419);
    }
    $t0 = microtime(true);
    try {
        $n = SearchIndex::rebuild();
        if (!SearchIndex::health()['ok']) {
            $n = (int) SearchIndex::repair()['rows'];
        }
    } catch (\Throwable $e) {
        if (SearchIndex::isBusy($e)) {
            return (new Response())->json(['ok' => false, 'error' => 'The site is busy writing (another rebuild or a restore is running) - try again in a few seconds.'], 503);
        }
        \Mova\Core\ErrorHandler::report('hq.search.rebuild', $e);
        try {
            $n = (int) SearchIndex::repair()['rows'];
        } catch (\Throwable $e2) {
            \Mova\Core\ErrorHandler::report('hq.search.repair', $e2);
            return (new Response())->json(['ok' => false, 'error' => 'Rebuild failed - see storage/logs/mova-errors.log.'], 500);
        }
    }
    return (new Response())->json([
        'ok' => true,
        'rows' => $n,
        'engine' => SearchIndex::engine(),
        'took_ms' => round((microtime(true) - $t0) * 1000, 1),
    ]);
});
