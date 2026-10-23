<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Media\MediaService;
use Mova\AI\AiJobService;
use Mova\AI\ImageGenService;

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
        $variants = $item['variants'] ?? [];
        $urlsByWidth = [];
        if (is_array($variants)) {
            foreach ($variants as $v) {
                $w = (int)($v['width'] ?? 0);
                if ($w > 0 && !empty($v['path'])) {
                    $urlsByWidth[$w] = $svc->url($item, $w);
                }
            }
        }
        ksort($urlsByWidth);
        $thumbUrl = $urlsByWidth ? reset($urlsByWidth) : $svc->url($item);
        $posterUrl = $svc->posterUrl($item);
        $out[] = [
            'id' => (int) $item['id'],
            'url' => $svc->url($item),
            'thumb_url' => $thumbUrl,
            'poster_url' => $posterUrl,
            'urls' => $urlsByWidth,
            'path' => $item['path'] ?? '',
            'original_name' => $item['original_name'] ?? '',
            'alt_text' => $item['alt_text'] ?? '',
            'description' => $item['description'] ?? '',
            'mime_type' => $item['mime_type'] ?? '',
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'extension' => $item['extension'] ?? '',
            'variants' => $variants,
            'exclude_from_gallery' => (int) ($item['exclude_from_gallery'] ?? 0),
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

$router->post('/media/update/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $id = (int) ($params['id'] ?? 0);
    $svc = new MediaService();
    $fields = [
        'original_name' => $req->input('original_name'),
        'alt_text'      => $req->input('alt_text'),
        'description'   => $req->input('description'),
    ];
    // Only pass keys that were actually sent
    $payload = [];
    foreach (['original_name', 'alt_text', 'description'] as $k) {
        if ($req->input($k) !== null) {
            $payload[$k] = (string) $req->input($k);
        }
    }
    $row = $svc->updateMeta($id, $payload);
    if (!$row) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['success' => false, 'error' => 'Not found']));
    }
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode([
            'success' => true,
            'media' => [
                'id' => (int) $row['id'],
                'original_name' => $row['original_name'] ?? '',
                'alt_text' => $row['alt_text'] ?? '',
                'description' => $row['description'] ?? '',
            ],
        ]));
});

$router->post('/media/replace/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $id = (int) ($params['id'] ?? 0);
    $file = $req->file('file');
    if (!$file && isset($_FILES['file']) && is_array($_FILES['file'])) {
        $file = $_FILES['file'];
    }
    if (!$file || !is_array($file)) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(400)
            ->body(json_encode(['success' => false, 'error' => 'No file uploaded.']));
    }
    try {
        $svc = new MediaService();
        $user = Auth::user();
        $uid = $user ? (int) ($user['id'] ?? 0) : null;
        // Always keep both old and new media; only retarget site occurrences
        $result = $svc->replaceByNewUpload($id, $file, $uid, false);
        $media = $result['new'];
        $urlsByWidth = [];
        foreach ((array) ($media['variants'] ?? []) as $v) {
            $w = (int) ($v['width'] ?? 0);
            if ($w > 0 && !empty($v['path'])) {
                $urlsByWidth[$w] = $svc->url($media, $w);
            }
        }
        ksort($urlsByWidth);
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->body(json_encode([
                'success' => true,
                'mode' => 'new_upload_retarget',
                'media' => $media,
                'url' => $svc->url($media),
                'urls' => $urlsByWidth,
                'old_id' => (int) ($result['old']['id'] ?? $id),
                'new_id' => (int) ($media['id'] ?? 0),
                'deleted_old' => !empty($result['deleted']),
                'retarget' => $result['retarget'] ?? [],
            ]));
    } catch (\Throwable $e) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(400)
            ->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
});

$router->get('/media/{id}/json', function (Request $req, array $params) {
    requireAuth();
    $id = (int) ($params['id'] ?? 0);
    $svc = new MediaService();
    $item = $svc->find($id);
    if (!$item) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['success' => false, 'error' => 'Not found']));
    }
    if (strpos((string) ($item['mime_type'] ?? ''), 'video/') === 0 && empty($item['poster_path'])) {
        try {
            $item = $svc->ensureVideoPoster($id) ?? $item;
        } catch (\Throwable $e) {
        }
    }
    $variants = $item['variants'] ?? [];
    $urlsByWidth = [];
    if (is_array($variants)) {
        foreach ($variants as $v) {
            $w = (int) ($v['width'] ?? 0);
            if ($w > 0 && !empty($v['path'])) {
                $urlsByWidth[$w] = $svc->url($item, $w);
            }
        }
    }
    ksort($urlsByWidth);
    $occurrences = $svc->findOccurrences($item);
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode([
            'success' => true,
            'media' => [
                'id' => (int) $item['id'],
                'url' => $svc->url($item),
                'poster_url' => $svc->posterUrl($item),
                'urls' => $urlsByWidth,
                'path' => $item['path'] ?? '',
                'original_name' => $item['original_name'] ?? '',
                'alt_text' => $item['alt_text'] ?? '',
                'description' => $item['description'] ?? '',
                'mime_type' => $item['mime_type'] ?? '',
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
                'extension' => $item['extension'] ?? '',
                'exclude_from_gallery' => (int) ($item['exclude_from_gallery'] ?? 0),
                'occurrences' => $occurrences,
            ],
        ]));
});


$router->post('/media/gallery-exclude/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $id = (int) ($params['id'] ?? 0);
    $exclude = filter_var($req->input('exclude', '1'), FILTER_VALIDATE_BOOLEAN)
        || $req->input('exclude') === '1'
        || $req->input('exclude') === 1;
    // Explicit 0/false
    $raw = $req->input('exclude', '1');
    if ($raw === '0' || $raw === 0 || $raw === false || $raw === 'false') {
        $exclude = false;
    } else {
        $exclude = true;
    }
    $svc = new MediaService();
    $ok = $svc->setExcludeFromGallery($id, $exclude);
    $row = $svc->find($id);
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode([
            'success' => (bool) $ok,
            'exclude_from_gallery' => (int) ($row['exclude_from_gallery'] ?? ($exclude ? 1 : 0)),
        ]));
});


$router->post('/media/generate', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(403)
            ->body(json_encode(['ok' => false, 'error' => 'Forbidden']));
    }
    $user = Auth::user();
    $uid = $user ? (int) ($user['id'] ?? 0) : 0;
    $prompt = trim((string) $req->post('prompt', ''));
    $aspect = strtolower(trim((string) $req->post('aspect', 'square')));
    $negative = trim((string) $req->post('negative_prompt', ''));
    $replaceMediaId = (int) $req->post('replace_media_id', 0);
    if ($prompt === '') {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(400)
            ->body(json_encode(['ok' => false, 'error' => 'Prompt is required']));
    }
    if (!isset(\Mova\AI\ImageGenService::ASPECTS[$aspect])) {
        $aspect = 'square';
    }
    if ($replaceMediaId > 0) {
        $svc = new MediaService();
        $existing = $svc->find($replaceMediaId);
        if (!$existing) {
            return (new Response())
                ->header('Content-Type', 'application/json')
                ->status(404)
                ->body(json_encode(['ok' => false, 'error' => 'Media to replace not found']));
        }
        $mime = (string) ($existing['mime_type'] ?? '');
        if (strpos($mime, 'image/') !== 0) {
            return (new Response())
                ->header('Content-Type', 'application/json')
                ->status(400)
                ->body(json_encode(['ok' => false, 'error' => 'Only image media can be regenerated']));
        }
    }
    try {
        $jobs = new \Mova\AI\AiJobService();
        $ctx = [
            'aspect' => $aspect,
            'negative_prompt' => $negative,
            'size' => (new \Mova\AI\ImageGenService())->sizeForAspect($aspect),
            'route' => '/hq/media',
            'area' => 'content',
        ];
        if ($replaceMediaId > 0) {
            $ctx['replace_media_id'] = $replaceMediaId;
        }
        $job = $jobs->enqueue(
            $uid,
            $prompt,
            null,
            $ctx,
            'image',
            $req->post('client_key') ? (string) $req->post('client_key') : null
        );
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->body(json_encode(['ok' => true, 'job' => $job]));
    } catch (\Throwable $e) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(400)
            ->body(json_encode(['ok' => false, 'error' => $e->getMessage()]));
    }
});

$router->get('/media/jobs/{id}', function (Request $req, array $params) {
    requireAuth();
    $uid = (int) Auth::id();
    $id = (int) ($params['id'] ?? 0);
    $jobs = new \Mova\AI\AiJobService();
    $job = $jobs->getJob($id, $uid);
    if (!$job || ($job['kind'] ?? '') !== 'image') {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['ok' => false, 'error' => 'Not found']));
    }
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode(['ok' => true, 'job' => $job]));
});

/** Process a single image job (long-running; ignore_user_abort inside service). */
$router->post('/media/jobs/{id}/process', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(403)
            ->body(json_encode(['ok' => false, 'error' => 'Forbidden']));
    }
    $uid = (int) Auth::id();
    $id = (int) ($params['id'] ?? 0);
    $jobs = new \Mova\AI\AiJobService();
    $existing = $jobs->getJob($id, $uid);
    if (!$existing || ($existing['kind'] ?? '') !== 'image') {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['ok' => false, 'error' => 'Not found']));
    }
    // Already finished — return as-is
    if (in_array($existing['status'] ?? '', ['done', 'failed', 'cancelled'], true)) {
        return (new Response())
            ->header('Content-Type', 'application/json')
            ->body(json_encode(['ok' => true, 'job' => $existing]));
    }
    $job = $jobs->processJob($id, $uid);
    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode(['ok' => true, 'job' => $job]));
});
