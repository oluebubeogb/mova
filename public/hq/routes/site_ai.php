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
    if ($action === 'save') {
        \MovaSiteAi\SiteAiService::saveConfig([
            'enabled' => $req->post('enabled') === '1',
            'name' => trim((string) $req->post('name', 'Site Assistant')),
            'welcome' => trim((string) $req->post('welcome', '')),
            'primary' => trim((string) $req->post('primary', '')),
            'accent' => trim((string) $req->post('accent', '')),
        ]);
    } elseif ($action === 'add_text') {
        $title = trim((string) $req->post('source_title', 'Note'));
        $text = trim((string) $req->post('source_text', ''));
        if ($text !== '') {
            \MovaSiteAi\KnowledgeBank::addTextSource($title !== '' ? $title : 'Note', 'text', $text);
        }
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
    } elseif ($action === 'upload' && !empty($_FILES['source_file']['tmp_name'])) {
        $f = $_FILES['source_file'];
        $name = (string) ($f['name'] ?? 'upload');
        $text = \MovaSiteAi\KnowledgeBank::extractTextFromUpload((string) $f['tmp_name'], $name);
        if (trim($text) !== '') {
            \MovaSiteAi\KnowledgeBank::addTextSource($name, 'file', mb_substr($text, 0, 100000), $name);
        }
    } elseif ($action === 'delete') {
        $id = (int) $req->post('source_id', 0);
        if ($id > 0) {
            \MovaSiteAi\KnowledgeBank::deleteSource($id);
        }
    }
    return (new Response())->redirect('/hq/site-ai');
});
