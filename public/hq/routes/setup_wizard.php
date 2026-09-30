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
require_once dirname($boot, 2) . '/src/Packs/SchoolPack.php';
require_once dirname($boot, 2) . '/src/Packs/OrgPack.php';
require_once $boot;

$router->get('/setup-wizard', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    \MovaSetupWizard\SetupWizardService::ensureActivated();
    return renderHq('setup_wizard/index', [
        'title' => 'Quick Setup Wizard',
        'packs' => \MovaSetupWizard\SetupWizardService::packList(),
        'ai_available' => class_exists(\Mova\AI\AiAssistService::class),
    ]);
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
