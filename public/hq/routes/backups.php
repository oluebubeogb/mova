<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Backup\BackupService;
use Mova\Core\Bootstrap;

/** @var \Mova\Core\Router $router */

$router->get('/backups', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator') && !Auth::can('manage_settings')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new BackupService();
    $all = $svc->listBackups();
    $allowedPerPage = [10, 25, 50, 100];
    $perPage = (int) $req->query('per_page', 25);
    if (!in_array($perPage, $allowedPerPage, true)) {
        $perPage = 25;
    }
    $page = max(1, (int) $req->query('page', 1));
    $total = count($all);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $backups = array_slice($all, $offset, $perPage);
    $stats = method_exists($svc, 'monthlyStats') ? $svc->monthlyStats() : [];
    return renderHq('backups/index', compact('backups', 'page', 'perPage', 'total', 'totalPages', 'allowedPerPage', 'stats') + [
        'title' => 'Backups',
    ]);
});

$router->post('/backups/create', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new BackupService();
    $path = method_exists($svc, 'createManaged') ? $svc->createManaged() : $svc->create();
    Audit::log('backup.created', 'backup', null, ['file' => basename($path)]);
    return (new Response())->redirect('/hq/backups?created=1');
});

$router->post('/backups/export-mova', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $password = (string) $req->post('password', '');
    if (!Auth::verifyPassword($password)) {
        return (new Response())->redirect('/hq/backups?error=' . rawurlencode('Password required — enter your account password to export.'));
    }
    $include = (string) $req->post('include_site_data', '') === '1'
        || (string) $req->post('include_site_data', '') === 'on';
    $svc = new BackupService();
    try {
        $path = $svc->exportInstallZip($include);
    } catch (\Throwable $e) {
        return (new Response())->redirect('/hq/backups?error=' . rawurlencode($e->getMessage()));
    }
    Audit::log('backup.export_mova', 'backup', null, [
        'file' => basename($path),
        'include_site_data' => $include,
    ]);
    // Stream download
    if (!is_file($path)) {
        return (new Response())->redirect('/hq/backups?error=export_failed');
    }
    $filename = basename($path);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
});

$router->get('/backups/download', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $file = basename((string) $req->query('file', ''));
    if ($file === '' || preg_match('/[\\\\\/]/', $file)) {
        return (new Response())->status(400)->body('Invalid file');
    }
    $svc = new BackupService();
    $path = null;
    foreach ($svc->listBackups() as $b) {
        if ($b['name'] === $file) {
            $path = $b['path'];
            break;
        }
    }
    if (!$path || !is_file($path)) {
        return (new Response())->status(404)->body('Not found');
    }
    $mime = str_ends_with(strtolower($file), '.zip') ? 'application/zip' : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
});

$router->post('/backups/delete', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $file = basename((string) $req->post('file', ''));
    $svc = new BackupService();
    $svc->delete($file);
    Audit::log('backup.deleted', 'backup', null, ['file' => $file]);
    return (new Response())->redirect('/hq/backups');
});

$router->post('/backups/restore', function (Request $req) {
    requireAuth();
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('Forbidden');
    }
    if (!Auth::hasRole('owner')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $file = basename((string) $req->post('file', ''));
    $svc = new BackupService();
    $path = null;
    foreach ($svc->listBackups() as $b) {
        if ($b['name'] === $file) {
            $path = $b['path'];
            break;
        }
    }
    if (!$path) {
        return (new Response())->redirect('/hq/backups?error=not_found');
    }
    try {
        $svc->restore($path);
        Audit::log('backup.restored', 'backup', null, ['file' => $file]);
    } catch (\Throwable $e) {
        return (new Response())->redirect('/hq/backups?error=' . rawurlencode($e->getMessage()));
    }
    return (new Response())->redirect('/hq/backups?restored=1');
});
