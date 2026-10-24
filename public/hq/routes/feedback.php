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
    return renderHq('feedback/index', [
        'title' => 'Feedback',
        'forms' => $svc->all(),
        'newCount' => $svc->countNew(),
    ]);
});

$router->get('/feedback/inbox', function (Request $req) {
    requireAuth();
    $svc = new FeedbackService();
    $formId = (int) $req->query('form', 0);
    $page = max(1, (int) $req->query('page', 1));
    $per = 25;
    $offset = ($page - 1) * $per;
    $counts = $svc->countByStatus($formId > 0 ? $formId : null);
    $total = $svc->countEntries($formId > 0 ? $formId : null);
    $entries = $svc->entries($formId > 0 ? $formId : null, $per, $offset);
    $form = $formId > 0 ? $svc->find($formId) : null;
    return renderHq('feedback/inbox', [
        'title' => 'Feedback inbox',
        'entries' => $entries,
        'forms' => $svc->all(),
        'formId' => $formId,
        'form' => $form,
        'counts' => $counts,
        'newCount' => $counts['new'],
        'page' => $page,
        'per' => $per,
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $per)),
    ]);
});

$router->get('/feedback/entry/{id}', function (Request $req, array $params) {
    requireAuth();
    $svc = new FeedbackService();
    $entry = $svc->findEntry((int) ($params['id'] ?? 0));
    if (!$entry) {
        return (new Response())->redirect('/hq/feedback/inbox');
    }
    if (($entry['status'] ?? '') === 'new') {
        $svc->markRead((int) $entry['id']);
        $entry['status'] = 'read';
    }
    // JSON for sidebar
    if ($req->query('format') === 'json' || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        return (new Response())->header('Content-Type', 'application/json')
            ->body(json_encode(['success' => true, 'entry' => $entry]));
    }
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
    if ($__deny = requirePerm('manage_content')) { return $__deny; }
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
    if ($__deny = requirePerm('manage_content')) { return $__deny; }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    (new FeedbackService())->deleteEntry((int) ($params['id'] ?? 0));
    $form = (int) $req->input('form', 0);
    return (new Response())->redirect('/hq/feedback/inbox' . ($form ? '?form=' . $form : ''));
});

$router->post('/feedback/entries/bulk', function (Request $req) {
    requireAuth();
    if ($__deny = requirePerm('manage_content')) { return $__deny; }
    if (!Csrf::validate($req->input('_csrf') ?: $req->input('_mova_csrf'))) {
        return (new Response())->header('Content-Type', 'application/json')->status(403)
            ->body(json_encode(['success' => false]));
    }
    $svc = new FeedbackService();
    $action = (string) $req->input('action', '');
    $ids = $req->input('ids', []);
    if (is_string($ids)) {
        $ids = array_filter(array_map('intval', explode(',', $ids)));
    }
    if (!is_array($ids)) {
        $ids = [];
    }
    if ($action === 'read') {
        $svc->markReadMany($ids);
    } elseif ($action === 'delete') {
        $svc->deleteEntries($ids);
    }
    $form = (int) $req->input('form', 0);
    return (new Response())->redirect('/hq/feedback/inbox' . ($form ? '?form=' . $form : ''));
});

$router->get('/feedback/export', function (Request $req) {
    requireAuth();
    $svc = new FeedbackService();
    $formId = (int) $req->query('form', 0);
    $idsParam = (string) $req->query('ids', '');
    $onlyIds = $idsParam !== '' ? array_filter(array_map('intval', explode(',', $idsParam))) : [];

    if ($formId > 0) {
        $form = $svc->find($formId);
        $fieldKeys = [];
        $fieldLabels = [];
        foreach (($form['fields'] ?? []) as $f) {
            $k = (string) ($f['key'] ?? '');
            if ($k === '') continue;
            $fieldKeys[] = $k;
            $fieldLabels[] = (string) ($f['label'] ?? $k);
        }
        $entries = $svc->entries($formId, 5000, 0);
        if ($onlyIds) {
            $entries = array_values(array_filter($entries, fn($e) => in_array((int)$e['id'], $onlyIds, true)));
        }
        $header = array_merge(['id', 'form_name'], $fieldLabels, ['status', 'created_at', 'page_url']);
        $lines = [implode(',', array_map(function ($h) {
            return '"' . str_replace('"', '""', $h) . '"';
        }, $header))];
        foreach ($entries as $e) {
            $payload = $e['payload'] ?? [];
            $row = [
                (int) $e['id'],
                '"' . str_replace('"', '""', (string) ($e['form_title'] ?? $form['title'] ?? '')) . '"',
            ];
            foreach ($fieldKeys as $k) {
                $v = $payload[$k] ?? '';
                if (is_array($v)) $v = implode('; ', $v);
                $row[] = '"' . str_replace('"', '""', (string) $v) . '"';
            }
            $row[] = $e['status'] ?? '';
            $row[] = $e['created_at'] ?? '';
            $row[] = '"' . str_replace('"', '""', (string) ($e['page_url'] ?? '')) . '"';
            $lines[] = implode(',', $row);
        }
        $filename = 'feedback-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($form['slug'] ?? 'form')) . '.csv';
    } else {
        $entries = $svc->entries(null, 5000, 0);
        if ($onlyIds) {
            $entries = array_values(array_filter($entries, fn($e) => in_array((int)$e['id'], $onlyIds, true)));
        }
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
        $filename = 'feedback-export.csv';
    }
    return (new Response())
        ->header('Content-Type', 'text/csv; charset=utf-8')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ->body(implode("\n", $lines) . "\n");
});
