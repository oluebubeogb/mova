<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Gallery\GalleryService;
use Mova\Media\MediaService;
use Mova\Cache\PageCache;

/** @var \Mova\Core\Router $router */

$router->get('/galleries', function (Request $req) {
    requireAuth();
    $svc = new GalleryService();
    $items = $svc->all(false, 100, 0);
    return renderHq('galleries/list', [
        'items' => $items,
        'title' => 'Galleries',
    ]);
});

$router->get('/galleries/new', function () {
    requireAuth();
    $media = (new MediaService())->all(100, 0);
    return renderHq('galleries/edit', [
        'gallery' => null,
        'items' => [],
        'mediaLibrary' => $media,
        'title' => 'New gallery',
    ]);
});

$router->get('/galleries/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    $svc = new GalleryService();
    $id = (int) ($params['id'] ?? 0);
    $gallery = $svc->find($id);
    if (!$gallery) {
        return (new Response())->redirect('/hq/galleries');
    }
    $items = $svc->items($id);
    $media = (new MediaService())->all(100, 0);
    return renderHq('galleries/edit', [
        'gallery' => $gallery,
        'items' => $items,
        'mediaLibrary' => $media,
        'title' => 'Edit gallery',
    ]);
});

$router->post('/galleries/save', function (Request $req) {
    requireAuth();
    if (!Csrf::validate($req->input('_csrf'))) {
        return (new Response())->status(403)->body('Invalid CSRF token');
    }
    $svc = new GalleryService();
    $id = (int) $req->input('id', 0);
    $mediaIds = $req->input('media_ids', []);
    if (!is_array($mediaIds)) {
        $mediaIds = array_filter(array_map('trim', explode(',', (string) $mediaIds)));
    }
    $mediaIds = array_values(array_unique(array_map('intval', $mediaIds)));

    $presetsRaw = $req->input('embed_presets_json', '[]');
    $presets = is_string($presetsRaw) ? json_decode($presetsRaw, true) : $presetsRaw;
    if (!is_array($presets)) {
        $presets = [];
    }
    $data = [
        'title' => (string) $req->input('title', ''),
        'slug' => (string) $req->input('slug', ''),
        'group_slug' => (string) $req->input('group_slug', ''),
        'description' => (string) $req->input('description', ''),
        'status' => (string) $req->input('status', 'published'),
        'cover_media_id' => $req->input('cover_media_id') !== '' ? (int) $req->input('cover_media_id') : null,
        'media_ids' => $mediaIds,
        'embed_layout' => (string) ($presets[0]['layout'] ?? $req->input('embed_layout', 'carousel')),
        'embed_presets' => $presets,
    ];

    try {
        if ($id > 0) {
            $svc->update($id, $data);
            Audit::log('gallery.update', 'gallery', $id);
        } else {
            $id = $svc->create($data);
            Audit::log('gallery.create', 'gallery', $id);
        }
        try {
            PageCache::flush();
        } catch (\Throwable $e) {
        }
        return (new Response())->redirect('/hq/galleries/edit/' . $id . '?saved=1');
    } catch (\Throwable $e) {
        $media = (new MediaService())->all(100, 0);
        $gallery = $id > 0 ? $svc->find($id) : null;
        $items = $id > 0 ? $svc->items($id) : [];
        return renderHq('galleries/edit', [
            'gallery' => $gallery,
            'items' => $items,
            'mediaLibrary' => $media,
            'title' => $id > 0 ? 'Edit gallery' : 'New gallery',
            'error' => $e->getMessage(),
            'form' => $data,
        ]);
    }
});

$router->post('/galleries/presets/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->header('Content-Type', 'application/json')->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => 'Save the gallery first']));
    }
    $svc = new GalleryService();
    $g = $svc->find($id);
    if (!$g) {
        return (new Response())->header('Content-Type', 'application/json')->status(404)
            ->body(json_encode(['success' => false, 'error' => 'Not found']));
    }
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    $presets = [];
    if (is_array($json) && isset($json['presets']) && is_array($json['presets'])) {
        $presets = $json['presets'];
    } else {
        $presetsRaw = $req->input('presets', $req->input('embed_presets_json', '[]'));
        $presets = is_string($presetsRaw) ? json_decode($presetsRaw, true) : $presetsRaw;
        if (!is_array($presets)) {
            $presets = [];
        }
    }
    try {
        $svc->update($id, [
            'embed_presets' => $presets,
            'embed_layout' => (string) ($presets[0]['layout'] ?? $g['embed_layout'] ?? 'carousel'),
        ]);
        try { PageCache::flush(); } catch (\Throwable $e) {}
        $fresh = $svc->find($id);
        return (new Response())->header('Content-Type', 'application/json')
            ->body(json_encode([
                'success' => true,
                'presets' => $svc->getPresets($fresh ?: $g),
            ]));
    } catch (\Throwable $e) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
});

$router->post('/galleries/delete/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate($req->input('_csrf'))) {
        return (new Response())->status(403)->body('Invalid CSRF token');
    }
    $id = (int) ($params['id'] ?? 0);
    $svc = new GalleryService();
    $svc->delete($id);
    Audit::log('gallery.delete', 'gallery', $id);
    try {
        PageCache::flush();
    } catch (\Throwable $e) {
    }
    return (new Response())->redirect('/hq/galleries');
});
