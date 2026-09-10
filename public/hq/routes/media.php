<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Media\MediaService;

/** @var \Mova\Core\Router $router */

$router->get('/media', function (Request $req) {
    requireAuth();
    $svc = new MediaService();
    $perPage = 25;
    $page = max(1, (int) $req->query('page', 1));
    $total = $svc->count();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $items = $svc->all($perPage, $offset);
    return renderHq('media/list', [
        'items' => $items,
        'title' => 'Media',
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
    ]);
});

$router->get('/media/json', function (Request $req) {
    requireAuth();
    $svc = new MediaService();
    $perPage = 25;
    $page = max(1, (int) $req->query('page', 1));
    $q = trim((string) $req->query('q', ''));
    $total = $svc->count();
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    // Load a reasonable window; client filters by search within the page.
    // For large libraries, prefer paging through pages of 25.
    $items = $svc->all($perPage, $offset);
    $out = [];
    foreach ($items as $item) {
        if ($q !== '') {
            $hay = strtolower(
                ($item['original_name'] ?? '') . ' ' .
                ($item['alt_text'] ?? '') . ' ' .
                ($item['path'] ?? '')
            );
            if (strpos($hay, strtolower($q)) === false) {
                continue;
            }
        }
        $out[] = [
            'id' => (int) $item['id'],
            'url' => $svc->url($item),
            'path' => $item['path'] ?? '',
            'original_name' => $item['original_name'] ?? '',
            'alt_text' => $item['alt_text'] ?? '',
            'mime_type' => $item['mime_type'] ?? '',
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'extension' => $item['extension'] ?? '',
            'variants' => $item['variants'] ?? [],
        ];
    }
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode([
            'success' => true,
            'items' => $out,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ]));
});

$router->post('/media/upload', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Invalid CSRF token. Refresh the page and try again.']));
    }

    // Prefer Request helper, fall back to $_FILES (some hosts/proxies)
    $file = $req->file('file');
    if (!$file && isset($_FILES['file']) && is_array($_FILES['file'])) {
        $file = $_FILES['file'];
    }
    if (!$file || !is_array($file)) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(400)
            ->body(json_encode(['success' => false, 'error' => 'No file uploaded. Field name must be "file".']));
    }

    try {
        $svc = new MediaService();
        $user = Auth::user();
        $media = $svc->upload($file, $user ? (int) ($user['id'] ?? 0) : null);
        if (!$media) {
            return (new Response())
                ->header('Content-Type', 'application/json')
                ->status(500)
                ->body(json_encode(['success' => false, 'error' => 'Upload saved but media record not found.']));
        }
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->body(json_encode([
                'success' => true,
                'media' => $media,
                'url' => $svc->url($media),
            ]));
    } catch (\Throwable $e) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(400)
            ->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
});

$router->post('/media/delete/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $id = (int) ($params['id'] ?? 0);
    $svc = new MediaService();
    $ok = $svc->delete($id);
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode(['success' => (bool) $ok]));
});
