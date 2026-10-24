<?php
/**
 * HQ → Operations → Text repair: find and fix "Nigeriau2019s"-style punctuation damage left by the
 * old AI JSON-recovery bug. Admin only; dry run first, writes only on an explicit POST.
 */

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\AI\UnicodeRepairService;

/** @var \Mova\Core\Router $router */

$router->get('/text-repair', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $id = (int) $req->query('id', 0);
    $findings = [];
    $error = null;
    try {
        $findings = (new UnicodeRepairService())->scan($id > 0 ? $id : null);
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    return renderHq('text_repair/index', [
        'findings' => $findings,
        'filterId' => $id,
        'error' => $error,
        'title' => 'Text repair',
    ]);
});

$router->post('/text-repair/apply', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $id = (int) $req->post('id', 0);
    $back = '/hq/text-repair' . ($id > 0 ? '?id=' . $id . '&' : '?');
    try {
        $res = (new UnicodeRepairService())->apply($id > 0 ? $id : null);
        try {
            Audit::log('text_repair.unicode', 'content', $id > 0 ? $id : null, ['fields' => $res['rows'], 'matches' => $res['matches']]);
        } catch (\Throwable $e) {
        }
        return (new Response())->redirect($back . 'fixed=' . (int) $res['matches'] . '&fields=' . (int) $res['rows'] . '&backup=' . rawurlencode(basename((string) ($res['backup'] ?? ''))));
    } catch (\Throwable $e) {
        return (new Response())->redirect($back . 'error=' . rawurlencode($e->getMessage()));
    }
});
