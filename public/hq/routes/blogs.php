<?php
declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Blog\BlogService;
use Mova\Content\ContentTypeService;
use Mova\Content\TaxonomyService;
use Mova\Content\ContentRepository;
use Mova\Cache\PageCache;

/** @var \Mova\Core\Router $router */

$router->get('/blogs', function () {
    requireAuth();
    $svc = new BlogService();
    return renderHq('blogs/list', [
        'items' => $svc->all(false, 100, 0),
        'title' => 'Blogs',
    ]);
});

$router->get('/blogs/new', function () {
    requireAuth();
    $types = (new ContentTypeService())->allTypes();
    $tax = new TaxonomyService();
    $posts = (new ContentRepository())->all(['status' => 'published'], 200, 0);
    return renderHq('blogs/edit', [
        'blog' => null,
        'types' => $types,
        'categories' => $tax->allCategories(),
        'tags' => $tax->allTags(),
        'posts' => $posts,
        'itemIds' => [],
        'title' => 'New blog',
    ]);
});

$router->get('/blogs/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    $svc = new BlogService();
    $id = (int) ($params['id'] ?? 0);
    $blog = $svc->find($id);
    if (!$blog) {
        return (new Response())->redirect('/hq/blogs');
    }
    $types = (new ContentTypeService())->allTypes();
    $tax = new TaxonomyService();
    $posts = (new ContentRepository())->all(['status' => 'published'], 200, 0);
    return renderHq('blogs/edit', [
        'blog' => $blog,
        'types' => $types,
        'categories' => $tax->allCategories(),
        'tags' => $tax->allTags(),
        'posts' => $posts,
        'itemIds' => $svc->itemIds($id),
        'title' => 'Edit blog',
    ]);
});

$router->post('/blogs/save', function (Request $req) {
    requireAuth();
    if (!Csrf::validate($req->input('_csrf') ?: $req->input('_mova_csrf'))) {
        return (new Response())->status(403)->body('Invalid CSRF token');
    }
    $svc = new BlogService();
    $id = (int) $req->input('id', 0);
    $types = $req->input('allowed_types', []);
    if (!is_array($types)) {
        $types = array_filter(array_map('trim', explode(',', (string) $types)));
    }
    $cats = $req->input('category_ids', []);
    if (!is_array($cats)) {
        $cats = array_filter(array_map('intval', explode(',', (string) $cats)));
    }
    $tags = $req->input('tag_ids', []);
    if (!is_array($tags)) {
        $tags = array_filter(array_map('intval', explode(',', (string) $tags)));
    }
    $contentIds = $req->input('content_ids', []);
    if (!is_array($contentIds)) {
        $contentIds = array_filter(array_map('intval', explode(',', (string) $contentIds)));
    }
    $data = [
        'title' => (string) $req->input('title', ''),
        'slug' => (string) $req->input('slug', ''),
        'description' => (string) $req->input('description', ''),
        'status' => (string) $req->input('status', 'published'),
        'mode' => (string) $req->input('mode', 'filter'),
        'allowed_types' => $types,
        'category_ids' => $cats,
        'tag_ids' => $tags,
        'sort' => (string) $req->input('sort', 'date_desc'),
        'per_page' => (int) $req->input('per_page', 12),
        'excerpt_chars' => (int) $req->input('excerpt_chars', 130),
        'template_mode' => (string) $req->input('template_mode', 'default'),
        'template_html' => (string) $req->input('template_html', ''),
        'content_ids' => $contentIds,
        'embed_options' => ['limit' => (int) $req->input('embed_limit', 6)],
    ];
    try {
        if ($id > 0) {
            $svc->update($id, $data);
            Audit::log('blog.update', 'blog', $id);
        } else {
            $id = $svc->create($data);
            Audit::log('blog.create', 'blog', $id);
        }
        try { PageCache::flush(); } catch (\Throwable $e) {}
        return (new Response())->redirect('/hq/blogs/edit/' . $id . '?saved=1');
    } catch (\Throwable $e) {
        $typesList = (new ContentTypeService())->allTypes();
        $tax = new TaxonomyService();
        $posts = (new ContentRepository())->all(['status' => 'published'], 200, 0);
        return renderHq('blogs/edit', [
            'blog' => $id > 0 ? $svc->find($id) : null,
            'types' => $typesList,
            'categories' => $tax->allCategories(),
            'tags' => $tax->allTags(),
            'posts' => $posts,
            'itemIds' => $id > 0 ? $svc->itemIds($id) : [],
            'title' => $id > 0 ? 'Edit blog' : 'New blog',
            'error' => $e->getMessage(),
            'form' => $data,
        ]);
    }
});

$router->post('/blogs/delete/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate($req->input('_csrf') ?: $req->input('_mova_csrf'))) {
        return (new Response())->status(403)->body('Invalid CSRF token');
    }
    $id = (int) ($params['id'] ?? 0);
    $svc = new BlogService();
    try {
        $svc->delete($id);
        Audit::log('blog.delete', 'blog', $id);
        try { PageCache::flush(); } catch (\Throwable $e) {}
    } catch (\Throwable $e) {
        return (new Response())->redirect('/hq/blogs?error=' . urlencode($e->getMessage()));
    }
    return (new Response())->redirect('/hq/blogs');
});
