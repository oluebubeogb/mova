<?php

declare(strict_types=1);

use Mova\Core\Response;
use Mova\Core\HealthService;
use Mova\Core\Database;
use Mova\Auth\Auth;
use Mova\Core\Requirements;

/** @var \Mova\Core\Router $router */

$router->get('/health', function () {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $report = (new HealthService())->report();
    $recentAudit = [];
    try {
        $recentAudit = Database::fetchAll(
            "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 25"
        );
    } catch (\Throwable $e) {
    }
    $recentLogins = [];
    try {
        $recentLogins = Database::fetchAll(
            "SELECT * FROM login_history ORDER BY created_at DESC LIMIT 20"
        );
    } catch (\Throwable $e) {
    }
    $reqSummary = Requirements::summary();
    return renderHq('health/index', [
        'requirements' => $reqSummary,
        'report' => $report,
        'recentAudit' => $recentAudit,
        'recentLogins' => $recentLogins,
        'title' => 'System health',
    ]);
});
