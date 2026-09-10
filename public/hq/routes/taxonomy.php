<?php

declare(strict_types=1);
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Csrf;
use Mova\Content\TaxonomyService;
use Mova\Cache\PageCache;

/** @var \Mova\Core\Router $router */

$router->get('/categories', function () {
    requireAuth();
    $tax = new TaxonomyService();
    $categories = $tax->allCategories();
    return renderHq('taxonomy/categories', compact('categories'));
});

$router->post('/categories', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $tax = new TaxonomyService();
    $action = (string) $req->post('action', 'create');
    if ($action === 'delete') {
        $tax->deleteCategory((int) $req->post('id', 0));
    } elseif ($action === 'update') {
        $tax->updateCategory((int) $req->post('id', 0), [
            'name'        => $req->post('name', ''),
            'slug'        => $req->post('slug', ''),
            'description' => $req->post('description', ''),
            'parent_id'   => $req->post('parent_id', ''),
        ]);
    } else {
        $tax->createCategory([
            'name'        => $req->post('name', ''),
            'slug'        => $req->post('slug', ''),
            'description' => $req->post('description', ''),
            'parent_id'   => $req->post('parent_id', ''),
        ]);
    }
    PageCache::flush();
    return (new Response())->redirect('/hq/categories');
});

// ---------- Taxonomy: Tags ----------
$router->get('/tags', function () {
    requireAuth();
    $tax = new TaxonomyService();
    $tags = $tax->allTags();
    return renderHq('taxonomy/tags', compact('tags'));
});

$router->post('/tags', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }
    $tax = new TaxonomyService();
    $action = (string) $req->post('action', 'create');
    if ($action === 'delete') {
        $tax->deleteTag((int) $req->post('id', 0));
    } elseif ($action === 'update') {
        $tax->updateTag((int) $req->post('id', 0), (string) $req->post('name', ''), (string) $req->post('slug', ''));
    } else {
        $tax->createTag((string) $req->post('name', ''), (string) $req->post('slug', '') ?: null);
    }
    PageCache::flush();
    return (new Response())->redirect('/hq/tags');
});
