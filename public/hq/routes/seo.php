<?php
declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Csrf;
use Mova\Media\MediaService;
use Mova\Content\ContentRepository;
use Mova\Auth\Auth;

/** @var \Mova\Core\Router $router */

$router->get('/seo/media', function (Request $req) {
    requireAuth();
    $svc = new MediaService();
    $q = trim((string) $req->query('q', ''));
    $items = $svc->all(500, 0);
    if ($q !== '') {
        $ql = mb_strtolower($q);
        $items = array_values(array_filter($items, static function ($m) use ($ql) {
            $hay = mb_strtolower(implode(' ', [
                (string) ($m['id'] ?? ''),
                (string) ($m['original_name'] ?? ''),
                (string) ($m['alt_text'] ?? ''),
                (string) ($m['description'] ?? ''),
                (string) ($m['path'] ?? ''),
            ]));
            return mb_strpos($hay, $ql) !== false;
        }));
    }
    $rows = [];
    foreach ($items as $m) {
        $url = $svc->url($m);
        $thumb = $url;
        $variants = $m['variants'] ?? [];
        if (is_array($variants) && $variants) {
            usort($variants, static function ($a, $b) {
                return ((int) ($a['width'] ?? 0)) <=> ((int) ($b['width'] ?? 0));
            });
            $thumb = $svc->url($m, (int) ($variants[0]['width'] ?? 0)) ?: $url;
        }
        $rows[] = [
            'id' => (int) $m['id'],
            'url' => $url,
            'thumb' => $thumb,
            'name' => (string) ($m['original_name'] ?? ''),
            'alt' => (string) ($m['alt_text'] ?? ''),
            'desc' => (string) ($m['description'] ?? ''),
            'mime' => (string) ($m['mime_type'] ?? ''),
        ];
    }
    return renderHq('seo/media', [
        'title' => 'Media SEO',
        'rows' => $rows,
        'q' => $q,
    ]);
});

$router->post('/seo/media/export', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $idsRaw = (string) $req->post('ids', '');
    $ids = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $idsRaw) ?: [])));
    if ($ids === []) {
        return (new Response())->status(400)->body('Mark at least one row to export.');
    }
    $svc = new MediaService();
    $lines = ['id,url,name,alt,desc'];
    foreach ($ids as $id) {
        $m = $svc->find($id);
        if (!$m) {
            continue;
        }
        $url = $svc->url($m);
        $lines[] = seo_csv_row([
            (string) $id,
            $url,
            (string) ($m['original_name'] ?? ''),
            (string) ($m['alt_text'] ?? ''),
            (string) ($m['description'] ?? ''),
        ]);
    }
    $csv = implode("\n", $lines) . "\n";
    return (new Response())
        ->header('Content-Type', 'text/csv; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="media-seo-export.csv"')
        ->body($csv);
});

$router->post('/seo/media/import', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->header('Content-Type', 'application/json')->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $file = $req->file('file') ?: ($_FILES['file'] ?? null);
    if (!$file || !is_array($file) || empty($file['tmp_name'])) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => 'No CSV uploaded']));
    }
    $parsed = seo_parse_csv_file($file['tmp_name']);
    if (!empty($parsed['error'])) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => $parsed['error']]));
    }
    $svc = new MediaService();
    // Index media by id and by url aliases
    $all = $svc->all(2000, 0);
    $byId = [];
    $byUrl = [];
    foreach ($all as $m) {
        $byId[(int) $m['id']] = $m;
        foreach ($svc->urlAliases($m) as $a) {
            $byUrl[mb_strtolower($a)] = $m;
        }
        $u = mb_strtolower($svc->url($m));
        $byUrl[$u] = $m;
    }

    $ok = [];
    $fail = [];
    foreach ($parsed['rows'] as $i => $row) {
        $lineNo = $i + 2; // header is line 1
        $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : 0;
        $url = trim((string) ($row['url'] ?? ''));
        if ($id <= 0 && $url === '') {
            $fail[] = ['line' => $lineNo, 'reason' => 'Missing id and url'];
            continue;
        }
        $media = null;
        if ($id > 0 && $url !== '') {
            $candidate = $byId[$id] ?? null;
            if (!$candidate) {
                $fail[] = ['line' => $lineNo, 'reason' => "id {$id} not found"];
                continue;
            }
            $aliases = array_map('mb_strtolower', $svc->urlAliases($candidate));
            $aliases[] = mb_strtolower($svc->url($candidate));
            if (!in_array(mb_strtolower($url), $aliases, true)) {
                $fail[] = ['line' => $lineNo, 'reason' => "id {$id} does not match url"];
                continue;
            }
            $media = $candidate;
        } elseif ($id > 0) {
            $media = $byId[$id] ?? null;
            if (!$media) {
                $fail[] = ['line' => $lineNo, 'reason' => "id {$id} not found"];
                continue;
            }
        } else {
            $media = $byUrl[mb_strtolower($url)] ?? null;
            if (!$media) {
                $fail[] = ['line' => $lineNo, 'reason' => 'url not found'];
                continue;
            }
        }

        $fields = [];
        if (array_key_exists('name', $row) && trim((string) $row['name']) !== '') {
            $fields['original_name'] = trim((string) $row['name']);
        }
        if (array_key_exists('alt', $row) && trim((string) $row['alt']) !== '') {
            $fields['alt_text'] = trim((string) $row['alt']);
        }
        if (array_key_exists('desc', $row) && trim((string) $row['desc']) !== '') {
            $fields['description'] = trim((string) $row['desc']);
        }
        // aliases for description/name headers
        if (!$fields && array_key_exists('description', $row) && trim((string) $row['description']) !== '') {
            $fields['description'] = trim((string) $row['description']);
        }
        if ($fields === []) {
            $fail[] = ['line' => $lineNo, 'reason' => 'No non-empty fields to update (empty cells left unchanged)'];
            continue;
        }
        try {
            $svc->updateMeta((int) $media['id'], $fields);
            $ok[] = [
                'line' => $lineNo,
                'id' => (int) $media['id'],
                'url' => $svc->url($media),
                'fields' => array_keys($fields),
            ];
        } catch (\Throwable $e) {
            $fail[] = ['line' => $lineNo, 'reason' => $e->getMessage()];
        }
    }

    return (new Response())->header('Content-Type', 'application/json')
        ->body(json_encode(['success' => true, 'imported' => $ok, 'failed' => $fail]));
});

$router->post('/seo/media/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->header('Content-Type', 'application/json')->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $id = (int) ($params['id'] ?? 0);
    $svc = new MediaService();
    $fields = [];
    if ($req->post('name') !== null || array_key_exists('name', $_POST)) {
        $fields['original_name'] = (string) $req->post('name', '');
    }
    if ($req->post('alt') !== null || array_key_exists('alt', $_POST)) {
        $fields['alt_text'] = (string) $req->post('alt', '');
    }
    if ($req->post('desc') !== null || array_key_exists('desc', $_POST)) {
        $fields['description'] = (string) $req->post('desc', '');
    }
    // Also accept JSON body field names
    $raw = file_get_contents('php://input');
    if ($raw && str_starts_with(trim($raw), '{')) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            if (array_key_exists('name', $json)) {
                $fields['original_name'] = (string) $json['name'];
            }
            if (array_key_exists('alt', $json)) {
                $fields['alt_text'] = (string) $json['alt'];
            }
            if (array_key_exists('desc', $json)) {
                $fields['description'] = (string) $json['desc'];
            }
        }
    }
    if ($fields === []) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => 'No fields']));
    }
    try {
        $media = $svc->updateMeta($id, $fields);
        if (!$media) {
            return (new Response())->header('Content-Type', 'application/json')->status(404)
                ->body(json_encode(['success' => false, 'error' => 'Not found']));
        }
        return (new Response())->header('Content-Type', 'application/json')
            ->body(json_encode([
                'success' => true,
                'media' => [
                    'id' => (int) $media['id'],
                    'name' => (string) ($media['original_name'] ?? ''),
                    'alt' => (string) ($media['alt_text'] ?? ''),
                    'desc' => (string) ($media['description'] ?? ''),
                ],
            ]));
    } catch (\Throwable $e) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
});

// ── Content SEO ──────────────────────────────────────────────

$router->get('/seo/content', function (Request $req) {
    requireAuth();
    $repo = new ContentRepository();
    $q = trim((string) $req->query('q', ''));
    $filters = [];
    if ($q !== '') {
        $filters['search'] = $q;
    }
    $items = $repo->all($filters, 500, 0);
    $rows = [];
    foreach ($items as $c) {
        $meta = $c['meta'] ?? $repo->getMeta((int) $c['id']);
        $rows[] = [
            'id' => (int) $c['id'],
            'title' => (string) ($c['title'] ?? ''),
            'slug' => (string) ($c['slug'] ?? ''),
            'status' => (string) ($c['status'] ?? ''),
            'seo_title' => (string) ($meta['seo_title'] ?? ''),
            'meta_description' => (string) ($meta['meta_description'] ?? $meta['seo_description'] ?? ''),
            'excerpt' => (string) ($c['excerpt'] ?? ''),
            'featured_image' => (string) ($c['featured_image'] ?? ''),
        ];
    }
    return renderHq('seo/content', [
        'title' => 'Content SEO',
        'rows' => $rows,
        'q' => $q,
    ]);
});

$router->post('/seo/content/export', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $idsRaw = (string) $req->post('ids', '');
    $ids = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $idsRaw) ?: [])));
    if ($ids === []) {
        return (new Response())->status(400)->body('Mark at least one row to export.');
    }
    $repo = new ContentRepository();
    $lines = ['id,slug,title,seo_title,meta_description,excerpt,featured_image,status'];
    foreach ($ids as $id) {
        $c = $repo->find($id);
        if (!$c) {
            continue;
        }
        $meta = $repo->getMeta($id);
        $lines[] = seo_csv_row([
            (string) $id,
            (string) ($c['slug'] ?? ''),
            (string) ($c['title'] ?? ''),
            (string) ($meta['seo_title'] ?? ''),
            (string) ($meta['meta_description'] ?? ''),
            (string) ($c['excerpt'] ?? ''),
            (string) ($c['featured_image'] ?? ''),
            (string) ($c['status'] ?? ''),
        ]);
    }
    $csv = implode("\n", $lines) . "\n";
    return (new Response())
        ->header('Content-Type', 'text/csv; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="content-seo-export.csv"')
        ->body($csv);
});

$router->post('/seo/content/import', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->header('Content-Type', 'application/json')->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $file = $req->file('file') ?: ($_FILES['file'] ?? null);
    if (!$file || !is_array($file) || empty($file['tmp_name'])) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => 'No CSV uploaded']));
    }
    $parsed = seo_parse_csv_file($file['tmp_name']);
    if (!empty($parsed['error'])) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => $parsed['error']]));
    }
    $repo = new ContentRepository();
    $ok = [];
    $fail = [];
    foreach ($parsed['rows'] as $i => $row) {
        $lineNo = $i + 2;
        $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : 0;
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($id <= 0 && $slug === '') {
            $fail[] = ['line' => $lineNo, 'reason' => 'Missing id and slug'];
            continue;
        }
        $content = null;
        if ($id > 0 && $slug !== '') {
            $content = $repo->find($id);
            if (!$content) {
                $fail[] = ['line' => $lineNo, 'reason' => "id {$id} not found"];
                continue;
            }
            if ((string) ($content['slug'] ?? '') !== $slug) {
                $fail[] = ['line' => $lineNo, 'reason' => "id {$id} does not match slug"];
                continue;
            }
        } elseif ($id > 0) {
            $content = $repo->find($id);
            if (!$content) {
                $fail[] = ['line' => $lineNo, 'reason' => "id {$id} not found"];
                continue;
            }
        } else {
            $content = $repo->findBySlugAnyStatus($slug);
            if (!$content) {
                $fail[] = ['line' => $lineNo, 'reason' => 'slug not found'];
                continue;
            }
        }
        $cid = (int) $content['id'];
        $data = [];
        $meta = $repo->getMeta($cid);
        $metaChanged = false;
        if (array_key_exists('title', $row) && trim((string) $row['title']) !== '') {
            $data['title'] = trim((string) $row['title']);
        }
        if (array_key_exists('excerpt', $row) && trim((string) $row['excerpt']) !== '') {
            $data['excerpt'] = trim((string) $row['excerpt']);
        }
        // featured_image: allow clear via special token, otherwise set non-empty URL/path
        if (array_key_exists('featured_image', $row)) {
            $fi = trim((string) $row['featured_image']);
            if ($fi === '-' || strcasecmp($fi, 'clear') === 0 || strcasecmp($fi, 'null') === 0) {
                $data['featured_image'] = '';
            } elseif ($fi !== '') {
                $data['featured_image'] = $fi;
            }
            // empty cell = leave unchanged (partial import)
        }
        if (array_key_exists('seo_title', $row) && trim((string) $row['seo_title']) !== '') {
            $meta['seo_title'] = trim((string) $row['seo_title']);
            $metaChanged = true;
        }
        if (array_key_exists('meta_description', $row) && trim((string) $row['meta_description']) !== '') {
            $meta['meta_description'] = trim((string) $row['meta_description']);
            $metaChanged = true;
        }
        if ($metaChanged) {
            $data['meta'] = $meta;
        }
        if ($data === []) {
            $fail[] = ['line' => $lineNo, 'reason' => 'No non-empty fields to update'];
            continue;
        }
        try {
            $repo->update($cid, $data);
            $ok[] = ['line' => $lineNo, 'id' => $cid, 'slug' => (string) ($content['slug'] ?? ''), 'fields' => array_keys($data)];
        } catch (\Throwable $e) {
            $fail[] = ['line' => $lineNo, 'reason' => $e->getMessage()];
        }
    }
    return (new Response())->header('Content-Type', 'application/json')
        ->body(json_encode(['success' => true, 'imported' => $ok, 'failed' => $fail]));
});

$router->post('/seo/content/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->header('Content-Type', 'application/json')->status(403)
            ->body(json_encode(['success' => false, 'error' => 'Forbidden']));
    }
    $id = (int) ($params['id'] ?? 0);
    $repo = new ContentRepository();
    $row = $repo->find($id);
    if (!$row) {
        return (new Response())->header('Content-Type', 'application/json')->status(404)
            ->body(json_encode(['success' => false, 'error' => 'Not found']));
    }
    $data = [];
    $meta = $repo->getMeta($id);
    $payload = $_POST;
    $raw = file_get_contents('php://input');
    if ($raw && str_starts_with(trim($raw), '{')) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $payload = array_merge($payload, $json);
        }
    }
    if (array_key_exists('title', $payload)) {
        $data['title'] = (string) $payload['title'];
    }
    if (array_key_exists('excerpt', $payload)) {
        $data['excerpt'] = (string) $payload['excerpt'];
    }
    if (array_key_exists('slug', $payload)) {
        $data['slug'] = (string) $payload['slug'];
    }
    if (array_key_exists('featured_image', $payload)) {
        $data['featured_image'] = (string) $payload['featured_image'];
    }
    if (array_key_exists('seo_title', $payload)) {
        $meta['seo_title'] = (string) $payload['seo_title'];
        $data['meta'] = $meta;
    }
    if (array_key_exists('meta_description', $payload)) {
        $meta['meta_description'] = (string) $payload['meta_description'];
        $data['meta'] = $meta;
    }
    if ($data === []) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => 'No fields']));
    }
    try {
        $repo->update($id, $data);
        $fresh = $repo->find($id);
        $freshMeta = $repo->getMeta($id);
        return (new Response())->header('Content-Type', 'application/json')
            ->body(json_encode([
                'success' => true,
                'content' => [
                    'id' => $id,
                    'title' => (string) ($fresh['title'] ?? ''),
                    'slug' => (string) ($fresh['slug'] ?? ''),
                    'excerpt' => (string) ($fresh['excerpt'] ?? ''),
                    'featured_image' => (string) ($fresh['featured_image'] ?? ''),
                    'seo_title' => (string) ($freshMeta['seo_title'] ?? ''),
                    'meta_description' => (string) ($freshMeta['meta_description'] ?? ''),
                ],
            ]));
    } catch (\Throwable $e) {
        return (new Response())->header('Content-Type', 'application/json')->status(400)
            ->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
});

/** CSV helpers (shared) */
function seo_csv_row(array $cols): string
{
    $out = [];
    foreach ($cols as $c) {
        $c = (string) $c;
        if (strpbrk($c, ",\"\n\r") !== false) {
            $c = '"' . str_replace('"', '""', $c) . '"';
        }
        $out[] = $c;
    }
    return implode(',', $out);
}

function seo_parse_csv_file(string $path): array
{
    $fh = fopen($path, 'r');
    if (!$fh) {
        return ['error' => 'Cannot read CSV', 'rows' => []];
    }
    $header = fgetcsv($fh);
    if (!$header || !is_array($header)) {
        fclose($fh);
        return ['error' => 'Empty CSV', 'rows' => []];
    }
    // Normalize headers
    $map = [];
    foreach ($header as $i => $h) {
        $h = strtolower(trim((string) $h));
        $h = str_replace([' ', '-'], '_', $h);
        if ($h === 'original_name' || $h === 'filename' || $h === 'file_name') {
            $h = 'name';
        }
        if ($h === 'alt_text' || $h === 'alttext') {
            $h = 'alt';
        }
        if ($h === 'description' || $h === 'descr') {
            $h = 'desc';
        }
        if ($h === 'seo-title') {
            $h = 'seo_title';
        }
        if ($h === 'meta-description' || $h === 'seo_description') {
            $h = 'meta_description';
        }
        if ($h === 'featured' || $h === 'image' || $h === 'feat_image' || $h === 'featuredimage' || $h === 'featured_img') {
            $h = 'featured_image';
        }
        $map[$i] = $h;
    }
    $headers = array_values($map);
    if (!in_array('id', $headers, true) && !in_array('url', $headers, true) && !in_array('slug', $headers, true)) {
        fclose($fh);
        return ['error' => 'CSV must include an id column and/or url (media) / slug (content)', 'rows' => []];
    }
    // id must not be in column index 1 if url is first? User said id cannot be in col2 - enforce: if both present, id should be first column
    if (isset($headers[1]) && $headers[1] === 'id' && isset($headers[0]) && $headers[0] !== 'id') {
        fclose($fh);
        return ['error' => 'id cannot be in column 2; put id in column 1 or use url/slug as the key column', 'rows' => []];
    }
    $rows = [];
    while (($data = fgetcsv($fh)) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }
        $row = [];
        foreach ($map as $i => $key) {
            $row[$key] = isset($data[$i]) ? (string) $data[$i] : '';
        }
        $rows[] = $row;
    }
    fclose($fh);
    return ['rows' => $rows];
}
