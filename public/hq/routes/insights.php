<?php

declare(strict_types=1);
use Mova\Core\Response;
use Mova\Auth\Auth;
use Mova\Insights\InsightsService;

/** @var \Mova\Core\Router $router */

$router->get('/insights', function () {
    requireAuth();
    if (!Auth::can('view_insights') && !Auth::hasRole('owner', 'administrator', 'editor')) {
        return (new Response())->status(403)->body('Forbidden');
    }
    $svc = new InsightsService();
    $summary = $svc->summary(30);
    $popular = $svc->popularContent(10, 30);
    $byDay = $svc->viewsByDay(14);
    $referrers = $svc->topReferrers(10, 30);
    $recent = $svc->recentViews(15);
    return renderHq('insights/index', array_merge(compact('summary', 'popular', 'byDay', 'referrers', 'recent'), ['title' => 'Insights']));
});
