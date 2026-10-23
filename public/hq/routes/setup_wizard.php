<?php

declare(strict_types=1);

use Mova\Auth\Auth;
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

$boot = dirname(__DIR__, 3) . '/mova-plugins/mova-setup-wizard/src/SetupWizardService.php';
if (!is_file($boot)) {
    return;
}
require_once dirname($boot, 2) . '/src/load.php';
require_once dirname($boot, 2) . '/src/Packs/SchoolPack.php';
require_once dirname($boot, 2) . '/src/Packs/OrgPack.php';
require_once $boot;

/** Owner/admin only. Returns a Response to short-circuit, or null. */
function mova_sw_guard(bool $post = false): ?Response
{
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->json(['ok' => false, 'error' => 'Only site owners and administrators can run Quick setup.'], 403);
    }
    if ($post && !Csrf::validate()) {
        return (new Response())->json(['ok' => false, 'error' => 'Your session expired — reload the page and try again.'], 403);
    }
    return null;
}

/** Draft as sent to the browser (copy bodies are large; only the AI job status is needed). */
function mova_sw_public_draft(array $d): array
{
    $out = array_intersect_key($d, array_flip(array_merge(\MovaSetupWizard\WizardDraft::FIELDS, ['updated_at'])));
    $job = (array) ($d['ai'] ?? []);
    unset($job['lock']);
    $out['ai'] = $job;
    $out['copy_pages'] = array_keys((array) ($d['copy'] ?? []));
    return $out;
}

$router->get('/setup-wizard', function () {
    if ($r = mova_sw_guard()) {
        return $r;
    }
    \MovaSetupWizard\SetupWizardService::ensureActivated();
    $kits = \MovaSetupWizard\KitRepository::list();
    foreach ($kits as &$k) {
        $full = \MovaSetupWizard\KitRepository::load((string) $k['id']);
        $k['demo'] = (array) ($full['demo'] ?? []);
        $k['page_list'] = array_map(static fn ($p) => ['slug' => (string) $p['slug'], 'title' => (string) ($p['title'] ?? $p['slug']), 'role' => (string) ($p['role'] ?? '')], (array) ($full['pages'] ?? []));
    }
    unset($k);
    $themes = [];
    if (function_exists('mova_theme_gallery_data')) {
        $themes = mova_theme_gallery_data()['themes'];
    }
    $ai = false;
    try {
        $ai = class_exists(\Mova\AI\AiAssistService::class) && (new \Mova\AI\AiAssistService())->isConfigured();
    } catch (\Throwable $e) {
    }
    $fontPairs = [];
    foreach (\MovaSetupWizard\WizardDraft::FONT_PAIRS as $id => $fp) {
        $fontPairs[] = ['id' => $id, 'label' => $fp['label'], 'heading' => $fp['heading'], 'body' => $fp['body'], 'sample' => $fp['sample']];
    }
    return renderHq('setup_wizard/index', [
        'title' => 'Quick setup',
        'packs' => \MovaSetupWizard\SetupWizardService::packList(),
        'kits' => $kits,
        'themes' => $themes,
        'presets' => \MovaSetupWizard\SetupWizardService::presetPalettes(),
        'fontPairs' => $fontPairs,
        'draft' => mova_sw_public_draft(\MovaSetupWizard\WizardDraft::load((int) Auth::id())),
        'ai_available' => $ai,
    ]);
});

$router->get('/setup-wizard/draft', function () {
    if ($r = mova_sw_guard()) {
        return $r;
    }
    return (new Response())->header('Cache-Control', 'no-store')->json(['ok' => true, 'draft' => mova_sw_public_draft(\MovaSetupWizard\WizardDraft::load((int) Auth::id()))]);
});

// Autosave: whitelisted fields only (see WizardDraft::merge).
$router->post('/setup-wizard/draft', function (Request $req) {
    if ($r = mova_sw_guard(true)) {
        return $r;
    }
    $uid = (int) Auth::id();
    $d = \MovaSetupWizard\WizardDraft::load($uid);
    $before = [$d['kit'] ?? '', $d['site_name'] ?? '', $d['tagline'] ?? '', $d['about'] ?? '', $d['city'] ?? ''];
    $d = \MovaSetupWizard\WizardDraft::merge($d, $_POST);
    if (!empty($_POST['reset'])) {
        $d = \MovaSetupWizard\WizardDraft::defaults();
    }
    // Facts or kit changed after AI copy was written: keep it, but tell the UI it may be stale.
    if (!empty($d['copy']) && $before !== [$d['kit'] ?? '', $d['site_name'] ?? '', $d['tagline'] ?? '', $d['about'] ?? '', $d['city'] ?? '']) {
        $d['ai']['stale'] = true;
        if (($before[0] ?? '') !== ($d['kit'] ?? '')) {
            $d['copy'] = [];
            $d['ai'] = [];
        }
    }
    $d = \MovaSetupWizard\WizardDraft::save($uid, $d);
    return (new Response())->json(['ok' => true, 'draft' => mova_sw_public_draft($d)]);
});

// Logo upload → media library; the URL is stored in the draft.
$router->post('/setup-wizard/logo', function (Request $req) {
    if ($r = mova_sw_guard(true)) {
        return $r;
    }
    $file = $_FILES['logo'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return (new Response())->json(['ok' => false, 'error' => 'Choose an image file (PNG, JPG, WebP or SVG, max 2 MB).'], 400);
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'], true) || (int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return (new Response())->json(['ok' => false, 'error' => 'Logos must be PNG, JPG, WebP or SVG and under 2 MB.'], 400);
    }
    try {
        $svc = new \Mova\Media\MediaService();
        $media = $svc->upload($file, (int) Auth::id());
        $url = (string) $svc->url($media);
    } catch (\Throwable $e) {
        return (new Response())->json(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    $uid = (int) Auth::id();
    $d = \MovaSetupWizard\WizardDraft::merge(\MovaSetupWizard\WizardDraft::load($uid), ['logo_url' => $url]);
    \MovaSetupWizard\WizardDraft::save($uid, $d);
    return (new Response())->json(['ok' => true, 'url' => $d['logo_url']]);
});

// AI copy — started once, then advanced one page per poll (works without workers on shared hosting / Docker).
$router->post('/setup-wizard/copy/start', function () {
    if ($r = mova_sw_guard(true)) {
        return $r;
    }
    $uid = (int) Auth::id();
    $d = \MovaSetupWizard\WizardDraft::load($uid);
    $kit = \MovaSetupWizard\KitRepository::load((string) ($d['kit'] ?? ''));
    if (!$kit) {
        return (new Response())->json(['ok' => false, 'error' => 'Choose a kit first.'], 422);
    }
    $d['copy'] = [];
    $d['ai'] = ['status' => 'running', 'done' => [], 'total' => count($kit['pages'] ?? []), 'started' => time(), 'current' => '', 'errors' => 0];
    $d['use_ai'] = true;
    $d = \MovaSetupWizard\WizardDraft::save($uid, $d);
    return (new Response())->json(['ok' => true, 'draft' => mova_sw_public_draft($d)]);
});

$router->post('/setup-wizard/copy/step', function () {
    if ($r = mova_sw_guard(true)) {
        return $r;
    }
    @set_time_limit(180);
    @ignore_user_abort(true);
    $uid = (int) Auth::id();
    // A running background worker (cron / supervisord) advances the job; the browser just watches.
    if (class_exists(\Mova\AI\AiWorker::class) && \Mova\AI\AiWorker::isAlive('wizard')) {
        $d = \MovaSetupWizard\WizardDraft::load($uid);
        \Mova\AI\AiWorker::nudge();
        return (new Response())->json(['ok' => true, 'busy' => true, 'worker' => true, 'draft' => mova_sw_public_draft($d)]);
    }
    $fresh = \MovaSetupWizard\SetupWizardService::advance($uid);
    $busy = !empty($fresh['_busy']);
    unset($fresh['_busy']);
    return (new Response())->json(['ok' => true, 'busy' => $busy, 'draft' => mova_sw_public_draft($fresh)]);
});

$router->post('/setup-wizard/copy/cancel', function () {
    if ($r = mova_sw_guard(true)) {
        return $r;
    }
    $uid = (int) Auth::id();
    $d = \MovaSetupWizard\WizardDraft::load($uid);
    if (!empty($_POST['discard'])) {
        $d['copy'] = [];
        $d['ai'] = [];
    } elseif (($d['ai']['status'] ?? '') === 'running') {
        $d['ai']['status'] = 'stopped';
        unset($d['ai']['lock']);
    }
    $d = \MovaSetupWizard\WizardDraft::save($uid, $d);
    return (new Response())->json(['ok' => true, 'draft' => mova_sw_public_draft($d)]);
});

$router->post('/setup-wizard/publish', function () {
    if ($r = mova_sw_guard(true)) {
        return $r;
    }
    @set_time_limit(120);
    $uid = (int) Auth::id();
    $d = \MovaSetupWizard\WizardDraft::merge(\MovaSetupWizard\WizardDraft::load($uid), $_POST);
    if (($d['ai']['status'] ?? '') === 'running') {
        $d['ai']['status'] = 'stopped'; // publish with whatever pages are rewritten so far
    }
    try {
        $res = \MovaSetupWizard\SetupWizardService::publishDraft($d);
    } catch (\Throwable $e) {
        return (new Response())->json(['ok' => false, 'error' => 'Publishing failed: ' . $e->getMessage()], 500);
    }
    if (!empty($res['ok'])) {
        $d['published_at'] = date('c');
        \MovaSetupWizard\WizardDraft::save($uid, $d);
        \Mova\Security\Audit::log('setup_wizard.published', 'kit', null, ['kit' => $d['kit'] ?? '', 'theme' => $d['theme'] ?? '']);
    }
    return (new Response())->json($res);
});

/**
 * Wizard CSS/JS are served straight from the plugin (single source of truth).
 * URL has no file extension on purpose so nginx's static-asset location does not
 * intercept it; works the same on shared hosting where /mova-plugins is not public.
 */
$router->get('/setup-wizard/asset/{kind}', function (Request $req, array $params = []) {
    requireAuth();
    $map = [
        'css' => ['assets/css/setup-wizard.css', 'text/css; charset=utf-8'],
        'js'  => ['assets/js/setup-wizard.js', 'application/javascript; charset=utf-8'],
    ];
    $kind = (string) ($params['kind'] ?? '');
    if (!isset($map[$kind])) {
        return (new Response())->status(404)->body('Not found');
    }
    $file = \Mova\Core\Bootstrap::path('plugins') . '/mova-setup-wizard/' . $map[$kind][0];
    if (!is_file($file)) {
        return (new Response())->status(404)->body('Not found');
    }
    $etag = '"' . md5((string) filemtime($file) . ':' . filesize($file)) . '"';
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        return (new Response())->status(304)->header('ETag', $etag);
    }
    return (new Response())
        ->header('Content-Type', $map[$kind][1])
        ->header('Cache-Control', 'private, max-age=3600')
        ->header('ETag', $etag)
        ->body((string) file_get_contents($file));
});

$router->get('/setup-wizard/kits', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->json(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $pack = (string) $req->query('pack', '');
    $kits = \MovaSetupWizard\KitRepository::list($pack !== '' ? $pack : null);
    return (new Response())->json(['ok' => true, 'kits' => $kits, 'root' => \MovaSetupWizard\KitRepository::kitsRoot()]);
});

$router->post('/setup-wizard/palettes', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->json(['ok' => false, 'error' => 'Forbidden'], 403);
    }
    $colors = [];
    foreach (['color1', 'color2', 'color3'] as $k) {
        $v = trim((string) $req->post($k, ''));
        if ($v !== '') {
            $colors[] = $v;
        }
    }
    $raw = $req->post('colors');
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $colors = array_merge($colors, $decoded);
        }
    }
    $palettes = \MovaSetupWizard\SetupWizardService::generatePalettes($colors);
    return (new Response())->json(['ok' => true, 'palettes' => $palettes]);
});

$router->post('/setup-wizard/run', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->json(['ok' => false, 'error' => 'Forbidden'], 403);
    }

    $paletteJson = (string) $req->post('palette_json', '');
    $palette = $paletteJson !== '' ? json_decode($paletteJson, true) : null;

    $input = [
        'pack_id' => (string) $req->post('pack_id', 'generic'),
        'kit_id' => (string) $req->post('kit_id', ''),
        'site_name' => (string) $req->post('site_name', ''),
        'tagline' => (string) $req->post('tagline', ''),
        'seed_text' => (string) $req->post('seed_text', ''),
        'about' => (string) $req->post('about', ''),
        'contact' => (string) $req->post('contact', ''),
        'status' => (string) $req->post('status', 'draft'),
        'use_ai' => $req->post('use_ai') === '1' || $req->post('use_ai') === 'true',
        'palette' => is_array($palette) ? $palette : null,
    ];

    if ($req->hasFile('seed_file')) {
        $file = $req->file('seed_file');
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($file['tmp_name'] ?? '');
            $name = strtolower((string) ($file['name'] ?? ''));
            if ($tmp !== '' && is_uploaded_file($tmp) && (str_ends_with($name, '.txt') || str_ends_with($name, '.md'))) {
                $text = (string) file_get_contents($tmp);
                if (strlen($text) > 50000) {
                    $text = substr($text, 0, 50000);
                }
                if (trim($text) !== '') {
                    $input['seed_text'] = trim($text);
                    if ($input['about'] === '') {
                        $input['about'] = $input['seed_text'];
                    }
                }
            }
        }
    }

    try {
        $result = \MovaSetupWizard\SetupWizardService::run($input);
        return (new Response())->json($result);
    } catch (\Throwable $e) {
        return (new Response())->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
});
