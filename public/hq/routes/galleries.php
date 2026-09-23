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

    $data = [
        'title' => (string) $req->input('title', ''),
        'slug' => (string) $req->input('slug', ''),
        'description' => (string) $req->input('description', ''),
        'status' => (string) $req->input('status', 'published'),
        'cover_media_id' => $req->input('cover_media_id') !== '' ? (int) $req->input('cover_media_id') : null,
        'media_ids' => $mediaIds,
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
