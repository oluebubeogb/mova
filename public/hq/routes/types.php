<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Content\ContentTypeService;

/** @var \Mova\Core\Router $router */

$router->get('/types', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new ContentTypeService();
    $types = $svc->allTypes();
    return renderHq('types/list', ['types' => $types, 'title' => 'Content types']);
});

$router->get('/types/new', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    return renderHq('types/edit', [
        'type' => null,
        'fields' => [],
        'fieldTypes' => ContentTypeService::FIELD_TYPES,
        'title' => 'New content type',
    ]);
});

$router->post('/types/new', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new ContentTypeService();
    $id = $svc->createType([
        'name'        => $req->post('name', ''),
        'slug'        => $req->post('slug', ''),
        'description' => $req->post('description', ''),
        'icon'        => $req->post('icon', 'fa-file'),
        'is_public'   => $req->post('is_public'),
        'schema_type' => $req->post('schema_type', 'WebPage'),
    ]);
    Audit::log('content_type.created', 'content_type', $id);
    return (new Response())->redirect('/hq/types/edit/' . $id);
});

$router->get('/types/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new ContentTypeService();
    $type = $svc->findType((int) $params['id']);
    if (!$type) {
        return (new Response())->redirect('/hq/types');
    }
    $fields = $svc->fieldsForType((int) $type['id']);
    return renderHq('types/edit', [
        'type' => $type,
        'fields' => $fields,
        'fieldTypes' => ContentTypeService::FIELD_TYPES,
        'title' => 'Edit type: ' . $type['name'],
    ]);
});

$router->post('/types/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') || !Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new ContentTypeService();
    $id = (int) $params['id'];
    $action = (string) $req->post('action', 'update');

    if ($action === 'add_field') {
        $svc->createField($id, [
            'name'        => $req->post('field_name', ''),
            'slug'        => $req->post('field_slug', ''),
            'field_type'  => $req->post('field_type', 'text'),
            'options'     => $req->post('field_options', ''),
            'is_required' => $req->post('field_required'),
        ]);
        return (new Response())->redirect('/hq/types/edit/' . $id . '?saved=1');
    }

    if ($action === 'delete_field') {
        $svc->deleteField((int) $req->post('field_id', 0));
        return (new Response())->redirect('/hq/types/edit/' . $id);
    }

    if ($action === 'delete_type') {
        $svc->deleteType($id);
        Audit::log('content_type.deleted', 'content_type', $id);
        return (new Response())->redirect('/hq/types');
    }

    $svc->updateType($id, [
        'name'        => $req->post('name', ''),
        'slug'        => $req->post('slug', ''),
        'description' => $req->post('description', ''),
        'icon'        => $req->post('icon', 'fa-file'),
        'is_public'   => $req->post('is_public'),
        'schema_type' => $req->post('schema_type', 'WebPage'),
    ]);
    Audit::log('content_type.updated', 'content_type', $id);
    return (new Response())->redirect('/hq/types/edit/' . $id . '?saved=1');
});
