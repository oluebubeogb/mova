<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;

/** @var \Mova\Core\Router $router */

$boot = dirname(__DIR__, 3) . '/mova-plugins/mova-site-ai/src/SiteAiService.php';
$kb = dirname(__DIR__, 3) . '/mova-plugins/mova-site-ai/src/KnowledgeBank.php';
if (!is_file($boot)) {
    return;
}
require_once $kb;
require_once $boot;

$router->get('/site-ai', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $cfg = \MovaSiteAi\SiteAiService::config();
    $palette = \MovaSiteAi\SiteAiService::paletteDefaults();
    $sources = \MovaSiteAi\KnowledgeBank::listSources();
    return renderHq('site_ai/index', [
        'title' => 'Site AI Engine',
        'cfg' => $cfg,
        'palette' => $palette,
        'sources' => $sources,
    ]);
});

$router->post('/site-ai', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $action = (string) $req->post('action', 'save');
    $layer = (string) $req->post('layer', 'settings');
    $redirectLayer = in_array($layer, ['settings', 'design', 'knowledge'], true) ? $layer : 'settings';

    if ($action === 'save') {
        $current = \MovaSiteAi\SiteAiService::config();
        $payload = [
            'enabled' => $req->post('enabled') === '1' || (!empty($current['enabled']) && $layer === 'design'),
            'name' => $layer === 'settings'
                ? trim((string) $req->post('name', 'Site Assistant'))
                : (string) ($current['name'] ?? 'Site Assistant'),
            'welcome' => $layer === 'settings'
                ? trim((string) $req->post('welcome', ''))
                : (string) ($current['welcome'] ?? ''),
            'primary' => $layer === 'design'
                ? trim((string) $req->post('primary', ''))
                : (string) ($current['primary'] ?? ''),
            'accent' => $layer === 'design'
                ? trim((string) $req->post('accent', ''))
                : (string) ($current['accent'] ?? ''),
            'bg_light' => $layer === 'design'
                ? trim((string) $req->post('bg_light', ''))
                : (string) ($current['bg_light'] ?? ''),
            'bg_dark' => $layer === 'design'
                ? trim((string) $req->post('bg_dark', ''))
                : (string) ($current['bg_dark'] ?? ''),
        ];
        // When saving design only, preserve enabled from checkbox absence
        if ($layer === 'design') {
            $payload['enabled'] = !empty($current['enabled']);
        }
        if ($layer === 'settings') {
            $payload['enabled'] = $req->post('enabled') === '1';
            $payload['primary'] = (string) ($current['primary'] ?? '');
            $payload['accent'] = (string) ($current['accent'] ?? '');
            $payload['bg_light'] = (string) ($current['bg_light'] ?? '');
            $payload['bg_dark'] = (string) ($current['bg_dark'] ?? '');
        }
        \MovaSiteAi\SiteAiService::saveConfig($payload);
        if (!empty($payload['enabled']) && class_exists(\Mova\Plugin\PluginManager::class)) {
            (new \Mova\Plugin\PluginManager())->activate('mova-site-ai');
        }
        if (class_exists(\Mova\Cache\PageCache::class)) {
            \Mova\Cache\PageCache::flush();
        }
    } elseif ($action === 'add_text') {
        $title = trim((string) $req->post('source_title', 'Note'));
        $text = trim((string) $req->post('source_text', ''));
        if ($text !== '') {
            \MovaSiteAi\KnowledgeBank::addTextSource($title !== '' ? $title : 'Note', 'text', $text);
        }
        $redirectLayer = 'knowledge';
    } elseif ($action === 'add_url') {
        $url = trim((string) $req->post('source_url', ''));
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            $ctx = stream_context_create(['http' => ['timeout' => 12, 'header' => "User-Agent: MovaSiteAI/1.0\r\n"]]);
            $body = @file_get_contents($url, false, $ctx);
            if (is_string($body) && $body !== '') {
                $text = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                \MovaSiteAi\KnowledgeBank::addTextSource($url, 'url', mb_substr($text, 0, 50000), $url);
            }
        }
        $redirectLayer = 'knowledge';
    } elseif ($action === 'upload_one') {
        // AJAX single-file upload with JSON response (progress UI)
        if (empty($_FILES['source_file']['tmp_name'])) {
            return (new Response())
                ->header('Content-Type', 'application/json; charset=utf-8')
                ->body(json_encode(['ok' => false, 'error' => 'No file']));
        }
        $f = $_FILES['source_file'];
        $name = (string) ($f['name'] ?? 'upload');
        $id = \MovaSiteAi\KnowledgeBank::queueUpload((string) $f['tmp_name'], $name);
        if ($id <= 0) {
            return (new Response())
                ->header('Content-Type', 'application/json; charset=utf-8')
                ->body(json_encode(['ok' => false, 'error' => 'Could not extract text']));
        }
        \MovaSiteAi\KnowledgeBank::processSource($id);
        return (new Response())
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->body(json_encode(['ok' => true, 'id' => $id]));
    } elseif ($action === 'upload') {
        // Classic multi-file form fallback
        $files = $_FILES['source_files'] ?? $_FILES['source_file'] ?? null;
        if ($files) {
            $names = $files['name'] ?? [];
            $tmps = $files['tmp_name'] ?? [];
            $errors = $files['error'] ?? [];
            if (!is_array($names)) {
                $names = [$names];
                $tmps = [$tmps];
                $errors = [$errors];
            }
            foreach ($names as $i => $name) {
                $tmp = $tmps[$i] ?? '';
                $err = $errors[$i] ?? UPLOAD_ERR_NO_FILE;
                if ($err !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
                    continue;
                }
                $id = \MovaSiteAi\KnowledgeBank::queueUpload($tmp, (string) $name);
                if ($id > 0) {
                    \MovaSiteAi\KnowledgeBank::processSource($id);
                }
            }
        }
        $redirectLayer = 'knowledge';
    } elseif ($action === 'delete') {
        $id = (int) $req->post('source_id', 0);
        if ($id > 0) {
            \MovaSiteAi\KnowledgeBank::deleteSource($id);
        }
        $redirectLayer = 'knowledge';
    }

    return (new Response())->redirect('/hq/site-ai?layer=' . rawurlencode($redirectLayer));
});
