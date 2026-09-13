<?php

declare(strict_types=1);
use Mova\Core\Bootstrap;
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Content\ContentRepository;
use Mova\Content\TaxonomyService;
use Mova\Content\ContentTypeService;
use Mova\Content\RelationService;
use Mova\Cache\PageCache;
use Mova\Webhook\WebhookService;

/** @var \Mova\Core\Router $router */

$router->get('/content', function (Request $req) {
    requireAuth();
    $repo = new ContentRepository();
    $status = $req->query('status', '');
    $type = $req->query('type', '');
    $filters = array_filter(['status' => $status, 'type' => $type]);

    $allowedPerPage = [10, 25, 50, 100];
    $perPage = (int) $req->query('per_page', Bootstrap::config('content.per_page', 25));
    if (!in_array($perPage, $allowedPerPage, true)) {
        $perPage = 25;
    }
    $page = max(1, (int) $req->query('page', 1));
    $total = $repo->count($filters);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $items = $repo->all($filters, $perPage, $offset);

    $typeSvc = new ContentTypeService();
    $types = $typeSvc->typesMap() ?: Bootstrap::config('content.types', []);
    $statuses = Bootstrap::config('content.statuses', []);
    return renderHq('content/list', compact(
        'items', 'types', 'statuses', 'status', 'type',
        'page', 'perPage', 'total', 'totalPages', 'allowedPerPage'
    ));
});

// New content
$router->get('/content/new', function () {
    requireAuth();
    $typeSvc = new ContentTypeService();
    $types = $typeSvc->typesMap() ?: Bootstrap::config('content.types', []);
    $tax = new TaxonomyService();
    $categories = $tax->allCategories();
    $selectedCategories = [];
    $tagString = '';
    $customFields = [];
    $fieldValues = [];
    $relatedIds = [];
    $relatedOptions = (new ContentRepository())->all(['status' => 'published'], 100);
    $revisions = [];
    return renderHq('content/edit', [
        'content' => null,
        'types' => $types,
        'categories' => $categories,
        'selectedCategories' => $selectedCategories,
        'tagString' => $tagString,
        'customFields' => $customFields,
        'fieldValues' => $fieldValues,
        'relatedIds' => $relatedIds,
        'relatedOptions' => $relatedOptions,
        'revisions' => $revisions,
    ]);
});

$router->post('/content/new', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }

    $repo = new ContentRepository();
    $title = trim((string) $req->post('title', ''));
    $slug = trim((string) $req->post('slug', ''));
    if (!$slug) {
        $slug = $repo->generateSlug($title);
    } else {
        $slug = $repo->generateSlug($slug);
    }

    $status = (string) $req->post('status', 'draft');
    $publishedAt = trim((string) $req->post('published_at', ''));
    if ($publishedAt !== '') {
        $publishedAt = date('c', strtotime($publishedAt));
    } else {
        $publishedAt = null;
    }

    // Body may arrive once or (legacy) as last of duplicates — normalize
    $rawBody = $req->post('body', '');
    if (is_array($rawBody)) {
        $rawBody = (string) end($rawBody);
    }
    $rawBody = (string) $rawBody;

    $data = [
        'type'           => $req->post('type', 'article'),
        'title'          => $title,
        'slug'           => $slug,
        'excerpt'        => trim((string) $req->post('excerpt', '')),
        'body'           => $rawBody,
        'status'         => $status,
        'author_id'      => Auth::id(),
        'featured_image' => trim((string) $req->post('featured_image', '')),
        'published_at'   => $publishedAt,
        'meta'           => [
            'seo_title'            => trim((string) $req->post('seo_title', '')),
            'meta_description'     => trim((string) $req->post('meta_description', '')),
            'og_title'             => trim((string) $req->post('og_title', '')),
            'og_description'       => trim((string) $req->post('og_description', '')),
            'robots'               => trim((string) $req->post('robots', 'index, follow')),
            'hide_article_chrome'  => $req->post('hide_article_chrome') ? '1' : '0',
        ],
    ];


    // Plugin hook: content.save.before
    $payload = ['request' => $req, 'data' => $data, 'errors' => [], 'warnings' => []];
    if (class_exists(\Mova\Plugin\PluginManager::class)) {
        $payload = \Mova\Plugin\PluginManager::applyFilters('content.save.before', $payload);
        $data = $payload['data'];
        if (!empty($payload['errors'])) {
            $msg = urlencode(implode(' | ', array_slice($payload['errors'], 0, 3)));
            return (new Response())->redirect('/hq/content/new?dev_error=' . $msg);
        }
    }

    $id = $repo->create($data);
    $repo->createRevision($id, $data, Auth::id());

    $tax = new TaxonomyService();
    $catIds = $req->post('categories', []);
    if (!is_array($catIds)) { $catIds = []; }
    $tax->syncContentCategories($id, $catIds);
    $tagNames = array_filter(array_map('trim', explode(',', (string) $req->post('tags', ''))));
    $tax->syncContentTagsByNames($id, $tagNames);

    $fieldValues = $req->post('fields', []);
    if (is_array($fieldValues)) {
        (new ContentTypeService())->saveFieldValues($id, $fieldValues);
    }
    $related = $req->post('related', []);
    if (!is_array($related)) { $related = []; }
    (new RelationService())->sync($id, $related, 'related');

    PageCache::invalidateContent($slug);
    Audit::log('content.created', 'content', $id, ['slug' => $slug]);
    if ($status === 'published') {
        (new WebhookService())->dispatch('content.published', ['id' => $id, 'slug' => $slug, 'title' => $title]);
    }

    return (new Response())->redirect('/hq/content/edit/' . $id);
});

// Edit content
$router->get('/content/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    $repo = new ContentRepository();
    $content = $repo->find((int) $params['id']);
    if (!$content) {
        return (new Response())->redirect('/hq/content');
    }
    $typeSvc = new ContentTypeService();
    $types = $typeSvc->typesMap() ?: Bootstrap::config('content.types', []);
    $tax = new TaxonomyService();
    $categories = $tax->allCategories();
    $selectedCategories = $tax->getContentCategoryIds((int) $content['id']);
    $contentTags = $tax->getContentTags((int) $content['id']);
    $tagString = implode(', ', array_column($contentTags, 'name'));
    $customFields = $typeSvc->fieldsForTypeSlug((string) $content['type']);
    $fieldValues = $typeSvc->getFieldValues((int) $content['id']);
    $relatedIds = (new RelationService())->getRelatedIds((int) $content['id'], 'related');
    $relatedOptions = $repo->all([], 100);
    $revisions = $repo->listRevisions((int) $content['id']);
    return renderHq('content/edit', compact(
        'content', 'types', 'categories', 'selectedCategories', 'tagString',
        'customFields', 'fieldValues', 'relatedIds', 'relatedOptions', 'revisions'
    ));
});

$router->post('/content/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }

    $id = (int) $params['id'];
    $repo = new ContentRepository();
    $existing = $repo->find($id);
    if (!$existing) {
        return (new Response())->redirect('/hq/content');
    }

    $title = trim((string) $req->post('title', ''));
    $slug = trim((string) $req->post('slug', ''));
    if ($slug !== $existing['slug']) {
        $slug = $repo->generateSlug($slug ?: $title, $id);
    }

    $status = (string) $req->post('status', 'draft');
    $publishedAt = trim((string) $req->post('published_at', ''));
    if ($publishedAt !== '') {
        $publishedAt = date('c', strtotime($publishedAt));
    } else {
        $publishedAt = $existing['published_at'] ?? null;
    }

    $rawBody = $req->post('body', '');
    if (is_array($rawBody)) {
        $rawBody = (string) end($rawBody);
    }
    $rawBody = (string) $rawBody;

    $data = [
        'type'           => $req->post('type', $existing['type']),
        'title'          => $title,
        'slug'           => $slug,
        'excerpt'        => trim((string) $req->post('excerpt', '')),
        'body'           => $rawBody,
        'status'         => $status,
        'featured_image' => trim((string) $req->post('featured_image', '')),
        'published_at'   => $publishedAt,
        'meta'           => [
            'seo_title'            => trim((string) $req->post('seo_title', '')),
            'meta_description'     => trim((string) $req->post('meta_description', '')),
            'og_title'             => trim((string) $req->post('og_title', '')),
            'og_description'       => trim((string) $req->post('og_description', '')),
            'robots'               => trim((string) $req->post('robots', 'index, follow')),
            'hide_article_chrome'  => $req->post('hide_article_chrome') ? '1' : '0',
        ],
    ];

    // Restore revision
    if ($req->post('action') === 'restore_revision') {
        $repo->restoreRevision($id, (int) $req->post('revision_id', 0), Auth::id());
        PageCache::invalidateContent($existing['slug']);
        Audit::log('content.revision_restored', 'content', $id);
        return (new Response())->redirect('/hq/content/edit/' . $id . '?saved=1');
    }


    // Plugin hook: content.save.before
    $payload = ['request' => $req, 'data' => $data, 'errors' => [], 'warnings' => [], 'content_id' => $id];
    if (class_exists(\Mova\Plugin\PluginManager::class)) {
        $payload = \Mova\Plugin\PluginManager::applyFilters('content.save.before', $payload);
        $data = $payload['data'];
        if (!empty($payload['errors'])) {
            $msg = urlencode(implode(' | ', array_slice($payload['errors'], 0, 3)));
            return (new Response())->redirect('/hq/content/edit/' . $id . '?dev_error=' . $msg);
        }
    }

    $repo->update($id, $data);
    $repo->createRevision($id, $data, Auth::id());

    $tax = new TaxonomyService();
    $catIds = $req->post('categories', []);
    if (!is_array($catIds)) { $catIds = []; }
    $tax->syncContentCategories($id, $catIds);
    $tagNames = array_filter(array_map('trim', explode(',', (string) $req->post('tags', ''))));
    $tax->syncContentTagsByNames($id, $tagNames);

    $fieldValues = $req->post('fields', []);
    if (is_array($fieldValues)) {
        (new ContentTypeService())->saveFieldValues($id, $fieldValues);
    }
    $related = $req->post('related', []);
    if (!is_array($related)) { $related = []; }
    (new RelationService())->sync($id, $related, 'related');

    PageCache::invalidateContent($slug);
    if ($existing['slug'] !== $slug) {
        PageCache::invalidateContent($existing['slug']);
    }
    Audit::log('content.updated', 'content', $id, ['slug' => $slug, 'status' => $status]);

    $hooks = new WebhookService();
    $hooks->dispatch('content.updated', ['id' => $id, 'slug' => $slug, 'title' => $title, 'status' => $status]);
    if ($status === 'published' && ($existing['status'] ?? '') !== 'published') {
        $hooks->dispatch('content.published', ['id' => $id, 'slug' => $slug, 'title' => $title]);
    }

    return (new Response())->redirect('/hq/content/edit/' . $id . '?saved=1');
});

// Delete / trash
$router->post('/content/trash/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $repo = new ContentRepository();
    $existing = $repo->find((int) $params['id']);
    $repo->trash((int) $params['id']);
    if ($existing) {
        PageCache::invalidateContent($existing['slug']);
        (new WebhookService())->dispatch('content.deleted', [
            'id' => (int) $existing['id'],
            'slug' => $existing['slug'],
            'title' => $existing['title'],
        ]);
    }
    return (new Response())->redirect('/hq/content?status=trash');
});

// Permanent delete (from trash)
$router->post('/content/delete/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $repo = new ContentRepository();
    $existing = $repo->find((int) $params['id']);
    if ($existing) {
        PageCache::invalidateContent($existing['slug']);
        $repo->delete((int) $params['id']);
        Audit::log('content.permanent_delete', 'content', (int) $params['id'], ['slug' => $existing['slug']]);
    }
    return (new Response())->redirect('/hq/content?status=trash');
});

// Restore from trash
$router->post('/content/restore/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $repo = new ContentRepository();
    $repo->restore((int) $params['id']);
    Audit::log('content.restored', 'content', (int) $params['id']);
    return (new Response())->redirect('/hq/content?status=trash');
});

// Bulk actions
$router->post('/content/bulk', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $ids = $req->post('ids', []);
    if (!is_array($ids)) {
        $ids = [];
    }
    $ids = array_values(array_filter(array_map('intval', $ids)));
    $action = (string) $req->post('bulk_action', '');
    $repo = new ContentRepository();
    $redirectStatus = (string) $req->post('return_status', '');

    foreach ($ids as $id) {
        $existing = $repo->find($id);
        if (!$existing) {
            continue;
        }
        switch ($action) {
            case 'trash':
                $repo->trash($id);
                PageCache::invalidateContent($existing['slug']);
                break;
            case 'restore':
                $repo->restore($id);
                break;
            case 'publish':
                $repo->update($id, ['status' => 'published']);
                PageCache::invalidateContent($existing['slug']);
                break;
            case 'draft':
                $repo->update($id, ['status' => 'draft']);
                PageCache::invalidateContent($existing['slug']);
                break;
            case 'delete':
                if (($existing['status'] ?? '') === 'trash') {
                    $repo->delete($id);
                    PageCache::invalidateContent($existing['slug']);
                    Audit::log('content.permanent_delete', 'content', $id, ['slug' => $existing['slug']]);
                }
                break;
        }
    }
    if ($ids) {
        Audit::log('content.bulk', null, null, ['action' => $action, 'ids' => $ids]);
    }
    $q = $redirectStatus !== '' ? '?status=' . urlencode($redirectStatus) : '';
    return (new Response())->redirect('/hq/content' . $q);
});
