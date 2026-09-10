<?php

declare(strict_types=1);
use Mova\Core\Database;
use Mova\Content\ContentRepository;

/** @var \Mova\Core\Router $router */

$router->get('/', function () {
    requireAuth();
    try {
        (new \Mova\Backup\BackupService())->ensureWeeklyBackup();
    } catch (\Throwable $e) {
    }
    $repo = new ContentRepository();
    $stats = [
        'published' => $repo->count(['status' => 'published']),
        'drafts'    => $repo->count(['status' => 'draft']),
        'media'     => Database::count('media'),
        'users'     => Database::count('users'),
    ];
    $recent = $repo->all([], 10);
    return renderHq('dashboard', compact('stats', 'recent'));
});
