<?php
declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Csrf;
use Mova\Feedback\FeedbackService;

/** @var \Mova\Core\Router $router */

$router->get('/feedback', function (Request $req) {
    requireAuth();
    $svc = new FeedbackService();
    FeedbackService::ensureDefaultContactForm();
    $forms = $svc->all(100);
    $newCount = $svc->countNew();
    return renderHq('feedback/index', [
        'title' => 'Feedback',
        'forms' => $forms,
        'newCount' => $newCount,
    ]);
});

$router->get('/feedback/inbox', function (Request $req) {
    requireAuth();
    $svc = new FeedbackService();
    $formId = (int) $req->query('form', 0);
    $entries = $svc->entries($formId > 0 ? $formId : null, 150, 0);
    $forms = $svc->all(100);
    return renderHq('feedback/inbox', [
        'title' => 'Feedback inbox',
        'entries' => $entries,
        'forms' => $forms,
        'formId' => $formId,
        'newCount' => $svc->countNew(),
    ]);
});

$router->get('/feedback/entry/{id}', function (Request $req, array $params) {
    requireAuth();
    $svc = new FeedbackService();
    $id = (int) ($params['id'] ?? 0);
    $entry = $svc->findEntry($id);
    if (!$entry) {
        return (new Response())->redirect('/hq/feedback/inbox');
    }
    $svc->markRead($id);
    $entry = $svc->findEntry($id);
    return renderHq('feedback/entry', [
        'title' => 'Feedback entry',
        'entry' => $entry,
    ]);
});

$router->get('/feedback/forms/new', function () {
    requireAuth();
    return renderHq('feedback/form_edit', [
        'title' => 'New feedback form',
        'form' => null,
    ]);
});

$router->get('/feedback/forms/edit/{id}', function (Request $req, array $params) {
    requireAuth();
    $svc = new FeedbackService();
    $form = $svc->find((int) ($params['id'] ?? 0));
    if (!$form) {
        return (new Response())->redirect('/hq/feedback');
    }
    return renderHq('feedback/form_edit', [
        'title' => 'Edit form',
        'form' => $form,
    ]);
});

$router->post('/feedback/forms/save', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new FeedbackService();
    $id = (int) $req->input('id', 0);
    $fieldsRaw = $req->input('fields_json', '[]');
    $fields = is_string($fieldsRaw) ? json_decode($fieldsRaw, true) : $fieldsRaw;
    if (!is_array($fields)) {
        $fields = [];
    }
    $data = [
        'title' => (string) $req->input('title', ''),
        'slug' => (string) $req->input('slug', ''),
        'description' => (string) $req->input('description', ''),
        'status' => (string) $req->input('status', 'published'),
        'success_message' => (string) $req->input('success_message', ''),
        'fields' => $fields,
    ];
    try {
        if ($id > 0) {
            $svc->update($id, $data);
        } else {
            $id = $svc->create($data);
        }
        return (new Response())->redirect('/hq/feedback/forms/edit/' . $id . '?saved=1');
    } catch (\Throwable $e) {
        return renderHq('feedback/form_edit', [
            'title' => $id ? 'Edit form' : 'New form',
            'form' => $id ? $svc->find($id) : null,
            'error' => $e->getMessage(),
            'formData' => $data,
        ]);
    }
});

$router->post('/feedback/entry/delete/{id}', function (Request $req, array $params) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    (new FeedbackService())->deleteEntry((int) ($params['id'] ?? 0));
    return (new Response())->redirect('/hq/feedback/inbox');
});

$router->get('/feedback/export', function (Request $req) {
    requireAuth();
    $svc = new FeedbackService();
    $formId = (int) $req->query('form', 0);
    $entries = $svc->entries($formId > 0 ? $formId : null, 5000, 0);
    $lines = ['id,form,status,created_at,page_url,payload'];
    foreach ($entries as $e) {
        $payload = json_encode($e['payload'] ?? [], JSON_UNESCAPED_UNICODE);
        $payload = '"' . str_replace('"', '""', $payload) . '"';
        $lines[] = implode(',', [
            (int) $e['id'],
            '"' . str_replace('"', '""', (string) ($e['form_title'] ?? '')) . '"',
            $e['status'] ?? '',
            $e['created_at'] ?? '',
            '"' . str_replace('"', '""', (string) ($e['page_url'] ?? '')) . '"',
            $payload,
        ]);
    }
    return (new Response())
        ->header('Content-Type', 'text/csv; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="feedback-export.csv"')
        ->body(implode("\n", $lines) . "\n");
});
