<?php
/**
 * Mova — public REST API (Phase 5)
 *
 * Stateless JSON API under /api/v1, authenticated with an HQ-issued API key:
 *
 *   Authorization: Bearer <key>      (preferred)
 *   X-Api-Key: <key>                 (fallback for hosts that strip Authorization)
 *
 * Every request is validated with ApiKeyService::authenticate() and every route
 * declares the scope it needs. No session or cookies are used; CSRF does not apply.
 *
 * Scopes: content:read, content:write, media:read, taxonomy:read, site:read.
 * Legacy keys: "read" = every :read scope, "write" = every :write scope, "*"/"admin" = all.
 */

declare(strict_types=1);

namespace Mova\Api;

use Mova\Cache\PageCache;
use Mova\Content\ContentRepository;
use Mova\Content\TaxonomyService;
use Mova\Core\Bootstrap;
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Media\MediaService;
use Mova\Security\Audit;
use Mova\Security\ClientIp;

final class RestApi
{
    public const PREFIX = '/api/v1';
    public const RATE_LIMIT = 120;
    public const RATE_WINDOW = 60;
    public const MAX_PER_PAGE = 100;

    /** Scopes offered in HQ, with a human description. */
    public const SCOPES = [
        'content:read'  => 'Read published content (and drafts when combined with content:write)',
        'content:write' => 'Create, update and trash content',
        'media:read'    => 'List media files and their URLs',
        'taxonomy:read' => 'List categories and tags',
        'site:read'     => 'Read site name, URL and API info',
    ];

    private const WRITABLE = [
        'title', 'slug', 'body', 'excerpt', 'status', 'type', 'featured_image',
        'template', 'canonical_url', 'visibility', 'parent_id', 'sort_order', 'published_at',
    ];

    private const META_WRITABLE = ['seo_title', 'meta_description', 'og_title', 'og_description', 'robots'];

    private ApiKeyService $keys;
    private ?array $key = null;
    private Request $request;

    public function __construct(?ApiKeyService $keys = null)
    {
        $this->keys = $keys ?? new ApiKeyService();
    }

    public static function handles(string $path): bool
    {
        return $path === self::PREFIX || strpos($path, self::PREFIX . '/') === 0;
    }

    /** Resolve the scope a key needs, honouring legacy read/write and wildcard keys. */
    public static function keyAllows(ApiKeyService $svc, array $key, string $scope): bool
    {
        if ($svc->hasScope($key, $scope)) {
            return true;
        }
        $scopes = $key['scopes'] ?? [];
        $suffix = substr($scope, (int) strrpos($scope, ':') + 1);
        if ($suffix === 'read' && (in_array('read', $scopes, true) || in_array('write', $scopes, true))) {
            return true;
        }
        if ($suffix === 'write' && in_array('write', $scopes, true)) {
            return true;
        }
        return false;
    }

    public function dispatch(Request $request, string $path): Response
    {
        $this->request = $request;
        $method = strtoupper($request->method());
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']) && $method === 'POST') {
            $override = strtoupper((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }
        $sub = substr($path, strlen(self::PREFIX));
        $sub = '/' . trim((string) $sub, '/');

        if ($method === 'OPTIONS') {
            return $this->json(['ok' => true], 204);
        }

        // 1. Authenticate — always through ApiKeyService::authenticate().
        $token = $this->bearerToken();
        if ($token === null) {
            return $this->error(401, 'missing_api_key', 'Send an API key as "Authorization: Bearer <key>" or "X-Api-Key: <key>".')
                ->header('WWW-Authenticate', 'Bearer realm="mova"');
        }
        $this->key = $this->keys->authenticate($token);
        if ($this->key === null) {
            return $this->error(401, 'invalid_api_key', 'The API key is invalid, revoked or expired.')
                ->header('WWW-Authenticate', 'Bearer realm="mova", error="invalid_token"');
        }

        // 2. Rate limit per key.
        try {
            $ok = (new RateLimiter())->hit((int) $this->key['id'], ClientIp::get(), 'api:v1', self::RATE_LIMIT, self::RATE_WINDOW);
        } catch (\Throwable $e) {
            $ok = true; // never fail closed on a limiter storage hiccup
        }
        if (!$ok) {
            return $this->error(429, 'rate_limited', 'Too many requests. Limit is ' . self::RATE_LIMIT . ' per ' . self::RATE_WINDOW . ' seconds.')
                ->header('Retry-After', (string) self::RATE_WINDOW);
        }

        // 3. Route → [scope, handler].
        try {
            return $this->route($method, $sub);
        } catch (\Throwable $e) {
            error_log('[mova api] ' . $e->getMessage());
            return $this->error(500, 'server_error', 'Unexpected server error.');
        }
    }

    private function route(string $method, string $sub): Response
    {
        if ($sub === '/') {
            return $this->guard($method, ['GET'], 'site:read', fn() => $this->index());
        }
        if ($sub === '/site') {
            return $this->guard($method, ['GET'], 'site:read', fn() => $this->site());
        }
        if ($sub === '/content') {
            if ($method === 'POST') {
                return $this->guard($method, ['POST'], 'content:write', fn() => $this->contentCreate());
            }
            return $this->guard($method, ['GET'], 'content:read', fn() => $this->contentList());
        }
        if (preg_match('#^/content/(\d+)$#', $sub, $m)) {
            $id = (int) $m[1];
            if (in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
                return $this->guard($method, ['PUT', 'PATCH', 'POST'], 'content:write', fn() => $this->contentUpdate($id));
            }
            if ($method === 'DELETE') {
                return $this->guard($method, ['DELETE'], 'content:write', fn() => $this->contentTrash($id));
            }
            return $this->guard($method, ['GET'], 'content:read', fn() => $this->contentShow($id));
        }
        if ($sub === '/search') {
            return $this->guard($method, ['GET'], 'content:read', fn() => $this->contentList((string) $this->request->query('q', '')));
        }
        if ($sub === '/media') {
            return $this->guard($method, ['GET'], 'media:read', fn() => $this->mediaList());
        }
        if (preg_match('#^/media/(\d+)$#', $sub, $m)) {
            $id = (int) $m[1];
            return $this->guard($method, ['GET'], 'media:read', fn() => $this->mediaShow($id));
        }
        if ($sub === '/categories') {
            return $this->guard($method, ['GET'], 'taxonomy:read', fn() => $this->json(['data' => array_map([$this, 'term'], (new TaxonomyService())->allCategories())]));
        }
        if ($sub === '/tags') {
            return $this->guard($method, ['GET'], 'taxonomy:read', fn() => $this->json(['data' => array_map([$this, 'term'], (new TaxonomyService())->allTags())]));
        }
        return $this->error(404, 'not_found', 'Unknown endpoint. GET ' . self::PREFIX . ' lists the available endpoints.');
    }

    private function guard(string $method, array $allowed, string $scope, callable $handler): Response
    {
        if (!in_array($method, $allowed, true)) {
            return $this->error(405, 'method_not_allowed', 'Use ' . implode(', ', $allowed) . '.')
                ->header('Allow', implode(', ', $allowed));
        }
        if (!self::keyAllows($this->keys, $this->key, $scope)) {
            // Note: no WWW-Authenticate here — PHP would silently turn the 403 into a 401.
            return $this->error(403, 'insufficient_scope', 'This API key lacks the "' . $scope . '" scope.')
                ->header('X-Mova-Required-Scope', $scope);
        }
        return $handler();
    }

    // ── Handlers ────────────────────────────────────────────────────────────

    private function index(): Response
    {
        return $this->json([
            'name' => 'Mova REST API',
            'version' => 'v1',
            'key' => ['name' => (string) ($this->key['name'] ?? ''), 'scopes' => array_values($this->key['scopes'] ?? [])],
            'endpoints' => [
                'GET ' . self::PREFIX . '/site' => 'site:read',
                'GET ' . self::PREFIX . '/content' => 'content:read',
                'GET ' . self::PREFIX . '/content/{id}' => 'content:read',
                'GET ' . self::PREFIX . '/search?q=' => 'content:read',
                'POST ' . self::PREFIX . '/content' => 'content:write',
                'PATCH ' . self::PREFIX . '/content/{id}' => 'content:write',
                'DELETE ' . self::PREFIX . '/content/{id}' => 'content:write',
                'GET ' . self::PREFIX . '/media' => 'media:read',
                'GET ' . self::PREFIX . '/media/{id}' => 'media:read',
                'GET ' . self::PREFIX . '/categories' => 'taxonomy:read',
                'GET ' . self::PREFIX . '/tags' => 'taxonomy:read',
            ],
            'rate_limit' => ['requests' => self::RATE_LIMIT, 'per_seconds' => self::RATE_WINDOW],
        ]);
    }

    private function site(): Response
    {
        $name = Bootstrap::config('app_name', 'Mova');
        try {
            if (function_exists('mova_setting')) {
                $name = (string) mova_setting('site_name', $name);
            }
        } catch (\Throwable $e) {
        }
        return $this->json(['data' => [
            'name' => (string) $name,
            'url' => rtrim((string) Bootstrap::config('app_url', ''), '/'),
            'language' => (string) Bootstrap::config('locale', 'en'),
            'api_version' => 'v1',
        ]]);
    }

    private function canSeeDrafts(): bool
    {
        return self::keyAllows($this->keys, $this->key, 'content:write');
    }

    private function contentList(?string $q = null): Response
    {
        $repo = new ContentRepository();
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = (int) $this->request->query('per_page', 20);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage ?: 20));
        $filters = [];
        $type = trim((string) $this->request->query('type', ''));
        if ($type !== '') {
            $filters['type'] = $type;
        }
        $search = trim((string) ($q ?? $this->request->query('search', '')));
        if ($search !== '') {
            $filters['search'] = mb_substr($search, 0, 120);
        }
        $status = trim((string) $this->request->query('status', ''));
        if (!$this->canSeeDrafts()) {
            $filters['status'] = 'published';
        } elseif ($status !== '') {
            $filters['status'] = $status;
        }

        $total = $repo->count($filters);
        $items = $repo->all($filters, $perPage, ($page - 1) * $perPage);
        if (!isset($filters['status'])) {
            // Trash is only listed when asked for explicitly.
            $items = array_values(array_filter($items, static fn($r) => ($r['status'] ?? '') !== 'trash'));
        }
        return $this->json([
            'data' => array_map(fn($r) => $this->contentOut($r, false), $items),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) max(1, ceil($total / $perPage))],
        ]);
    }

    private function contentShow(int $id): Response
    {
        $row = (new ContentRepository())->find($id);
        if (!$row || (!$this->canSeeDrafts() && ($row['status'] ?? '') !== 'published')) {
            return $this->error(404, 'not_found', 'Content not found.');
        }
        return $this->json(['data' => $this->contentOut($row, true)]);
    }

    private function contentCreate(): Response
    {
        $in = $this->input();
        if ($in === null) {
            return $this->error(400, 'invalid_json', 'Request body must be a JSON object.');
        }
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            return $this->error(422, 'validation_failed', 'Field "title" is required.', ['title' => 'required']);
        }
        $repo = new ContentRepository();
        $data = $this->writableFields($in, true);
        if (is_array($data) === false) {
            return $data;
        }
        $data['title'] = $title;
        $data['slug'] = $repo->generateSlug((string) ($data['slug'] ?? '') ?: $title);
        $data['type'] = $data['type'] ?? (string) Bootstrap::config('content.default_type', 'article');
        $data['status'] = $data['status'] ?? 'draft';
        $data['body'] = $data['body'] ?? '';
        $data['author_id'] = isset($this->key['created_by']) ? (int) $this->key['created_by'] : null;

        $id = $repo->create($data);
        $repo->createRevision($id, $data, $data['author_id']);
        $this->syncTaxonomy($id, $in);
        $this->afterWrite($data['slug']);
        Audit::log('api.content.created', 'content', $id, ['api_key_id' => (int) $this->key['id']]);
        $this->webhook($data['status'] === 'published' ? 'content.published' : null, $id, $data['slug'], $title);

        return $this->json(['data' => $this->contentOut((array) $repo->find($id), true)], 201)
            ->header('Location', self::PREFIX . '/content/' . $id);
    }

    private function contentUpdate(int $id): Response
    {
        $repo = new ContentRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            return $this->error(404, 'not_found', 'Content not found.');
        }
        $in = $this->input();
        if ($in === null) {
            return $this->error(400, 'invalid_json', 'Request body must be a JSON object.');
        }
        $data = $this->writableFields($in, false);
        if (is_array($data) === false) {
            return $data;
        }
        if (array_key_exists('title', $data) && trim((string) $data['title']) === '') {
            return $this->error(422, 'validation_failed', 'Field "title" cannot be empty.', ['title' => 'required']);
        }
        if (isset($data['slug'])) {
            $data['slug'] = $repo->generateSlug((string) $data['slug'], $id);
        }
        if (!array_key_exists('body', $data)) {
            // Keep excerpt regeneration consistent with the stored body.
            $data['body'] = (string) ($existing['body'] ?? '');
        }
        if (!array_key_exists('excerpt', $data)) {
            $data['excerpt'] = (string) ($existing['excerpt'] ?? '');
        }
        if (!$data && !isset($in['categories']) && !isset($in['tags'])) {
            return $this->error(422, 'validation_failed', 'Nothing to update.');
        }
        $repo->update($id, $data);
        $fresh = (array) $repo->find($id);
        $repo->createRevision($id, $fresh, isset($this->key['created_by']) ? (int) $this->key['created_by'] : null);
        $this->syncTaxonomy($id, $in);
        $this->afterWrite((string) $existing['slug'], (string) ($fresh['slug'] ?? ''));
        Audit::log('api.content.updated', 'content', $id, ['api_key_id' => (int) $this->key['id'], 'fields' => array_keys($data)]);
        $became = ($fresh['status'] ?? '') === 'published' && ($existing['status'] ?? '') !== 'published';
        $this->webhook($became ? 'content.published' : 'content.updated', $id, (string) $fresh['slug'], (string) $fresh['title']);

        return $this->json(['data' => $this->contentOut($fresh, true)]);
    }

    private function contentTrash(int $id): Response
    {
        $repo = new ContentRepository();
        $existing = $repo->find($id);
        if (!$existing) {
            return $this->error(404, 'not_found', 'Content not found.');
        }
        $repo->trash($id);
        $this->afterWrite((string) $existing['slug']);
        Audit::log('api.content.trashed', 'content', $id, ['api_key_id' => (int) $this->key['id']]);
        $this->webhook('content.deleted', $id, (string) $existing['slug'], (string) $existing['title']);
        return $this->json(['data' => ['id' => $id, 'status' => 'trash']]);
    }

    private function mediaList(): Response
    {
        $svc = new MediaService();
        $page = max(1, (int) $this->request->query('page', 1));
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) $this->request->query('per_page', 40) ?: 40));
        $total = $svc->count();
        $rows = $svc->all($perPage, ($page - 1) * $perPage);
        return $this->json([
            'data' => array_map(fn($m) => $this->mediaOut($svc, $m), $rows),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) max(1, ceil($total / $perPage))],
        ]);
    }

    private function mediaShow(int $id): Response
    {
        $svc = new MediaService();
        $m = $svc->find($id);
        if (!$m) {
            return $this->error(404, 'not_found', 'Media not found.');
        }
        return $this->json(['data' => $this->mediaOut($svc, $m)]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @return array|Response */
    private function writableFields(array $in, bool $creating)
    {
        $data = [];
        foreach (self::WRITABLE as $f) {
            if (!array_key_exists($f, $in)) {
                continue;
            }
            $v = $in[$f];
            if (is_array($v) || is_object($v)) {
                return $this->error(422, 'validation_failed', 'Field "' . $f . '" must be a scalar.', [$f => 'scalar']);
            }
            $data[$f] = $v === null ? null : (string) $v;
        }
        if (isset($data['status'])) {
            $allowed = array_keys((array) Bootstrap::config('content.statuses', [])) ?: ['draft', 'published', 'scheduled', 'trash'];
            if (!in_array($data['status'], $allowed, true)) {
                return $this->error(422, 'validation_failed', 'Invalid status. Allowed: ' . implode(', ', $allowed) . '.', ['status' => 'invalid']);
            }
        }
        if (isset($data['published_at']) && $data['published_at'] !== '') {
            $ts = strtotime((string) $data['published_at']);
            if ($ts === false) {
                return $this->error(422, 'validation_failed', 'Field "published_at" must be an ISO 8601 date.', ['published_at' => 'date']);
            }
            $data['published_at'] = date('c', $ts);
        }
        foreach (['parent_id', 'sort_order'] as $int) {
            if (array_key_exists($int, $data)) {
                $data[$int] = ($data[$int] === null || $data[$int] === '') ? null : (int) $data[$int];
            }
        }
        if (isset($data['title'])) {
            $data['title'] = mb_substr(trim((string) $data['title']), 0, 300);
        }
        if (isset($in['meta']) && is_array($in['meta'])) {
            $meta = [];
            foreach (self::META_WRITABLE as $k) {
                if (isset($in['meta'][$k]) && is_scalar($in['meta'][$k])) {
                    $meta[$k] = trim((string) $in['meta'][$k]);
                }
            }
            if ($meta) {
                $data['meta'] = $meta;
            }
        }
        return $data;
    }

    private function syncTaxonomy(int $id, array $in): void
    {
        $tax = new TaxonomyService();
        if (isset($in['categories']) && is_array($in['categories'])) {
            $tax->syncContentCategories($id, array_values(array_filter(array_map('intval', $in['categories']))));
        }
        if (isset($in['tags'])) {
            $names = is_array($in['tags']) ? $in['tags'] : explode(',', (string) $in['tags']);
            $tax->syncContentTagsByNames($id, array_values(array_filter(array_map(static fn($t) => trim((string) $t), $names))));
        }
    }

    private function afterWrite(string ...$slugs): void
    {
        try {
            foreach (array_filter($slugs) as $s) {
                PageCache::invalidateContent($s);
            }
        } catch (\Throwable $e) {
        }
    }

    private function webhook(?string $event, int $id, string $slug, string $title): void
    {
        if ($event === null || !class_exists(\Mova\Webhook\WebhookService::class)) {
            return;
        }
        try {
            (new \Mova\Webhook\WebhookService())->dispatch($event, ['id' => $id, 'slug' => $slug, 'title' => $title, 'source' => 'api']);
        } catch (\Throwable $e) {
        }
    }

    private function contentOut(array $r, bool $full): array
    {
        $out = [
            'id' => (int) $r['id'],
            'type' => (string) ($r['type'] ?? ''),
            'title' => (string) ($r['title'] ?? ''),
            'slug' => (string) ($r['slug'] ?? ''),
            'url' => '/' . ltrim((string) ($r['slug'] ?? ''), '/'),
            'status' => (string) ($r['status'] ?? ''),
            'excerpt' => (string) ($r['excerpt'] ?? ''),
            'featured_image' => $r['featured_image'] ?? null,
            'parent_id' => isset($r['parent_id']) ? (int) $r['parent_id'] : null,
            'published_at' => $r['published_at'] ?? null,
            'created_at' => $r['created_at'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
        ];
        if ($full) {
            $out['body'] = (string) ($r['body'] ?? '');
            $meta = is_array($r['meta'] ?? null) ? $r['meta'] : [];
            $out['meta'] = array_intersect_key($meta, array_flip(self::META_WRITABLE));
            try {
                $tax = new TaxonomyService();
                $out['categories'] = array_values($tax->getContentCategoryIds((int) $r['id']));
                $out['tags'] = array_values(array_map(static fn($t) => is_array($t) ? (string) ($t['name'] ?? '') : (string) $t, $tax->getContentTags((int) $r['id'])));
            } catch (\Throwable $e) {
            }
        }
        return $out;
    }

    private function mediaOut(MediaService $svc, array $m): array
    {
        $url = '';
        try {
            $url = $svc->url($m);
        } catch (\Throwable $e) {
        }
        return [
            'id' => (int) $m['id'],
            'filename' => (string) ($m['original_name'] ?? $m['filename'] ?? ''),
            'mime_type' => (string) ($m['mime_type'] ?? ''),
            'size' => isset($m['size']) ? (int) $m['size'] : null,
            'width' => isset($m['width']) ? (int) $m['width'] : null,
            'height' => isset($m['height']) ? (int) $m['height'] : null,
            'alt' => (string) ($m['alt_text'] ?? $m['alt'] ?? ''),
            'title' => (string) ($m['title'] ?? ''),
            'url' => $url,
            'created_at' => $m['created_at'] ?? null,
        ];
    }

    private function term(array $t): array
    {
        return ['id' => (int) $t['id'], 'name' => (string) ($t['name'] ?? ''), 'slug' => (string) ($t['slug'] ?? '')]
            + (isset($t['parent_id']) ? ['parent_id' => (int) $t['parent_id']] : []);
    }

    private function bearerToken(): ?string
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($h === '' && function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $k => $v) {
                if (strcasecmp((string) $k, 'Authorization') === 0) {
                    $h = (string) $v;
                    break;
                }
            }
        }
        if ($h !== '' && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $h, $m)) {
            return $m[1];
        }
        $x = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
        return $x !== '' ? $x : null;
    }

    private function input(): ?array
    {
        $raw = (string) file_get_contents('php://input');
        $ctype = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($raw !== '' && strpos($ctype, 'json') !== false) {
            if (strlen($raw) > 2 * 1024 * 1024) {
                return null;
            }
            $d = json_decode($raw, true);
            return is_array($d) && !array_is_list($d) ? $d : (is_array($d) && $d === [] ? [] : null);
        }
        if ($raw === '' && !$_POST) {
            return [];
        }
        if ($_POST) {
            return $_POST;
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }

    private function json(array $data, int $status = 200): Response
    {
        $r = (new Response())->json($data, $status);
        $r->header('Cache-Control', 'no-store');
        $r->header('X-Robots-Tag', 'noindex');
        if ($this->key !== null) {
            $r->header('X-RateLimit-Limit', (string) self::RATE_LIMIT);
        }
        return $r;
    }

    private function error(int $status, string $code, string $message, array $fields = []): Response
    {
        $err = ['code' => $code, 'message' => $message];
        if ($fields) {
            $err['fields'] = $fields;
        }
        return $this->json(['error' => $err], $status);
    }
}
