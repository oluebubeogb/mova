<?php

declare(strict_types=1);

use Mova\AI\AiConfig;
use Mova\AI\AiUsage;
use Mova\Auth\Auth;
use Mova\Core\Request;
use Mova\Core\Response;

/** @var \Mova\Core\Router $router */

$router->get('/ai-usage', function (Request $req) {
    requireAuth();
    if (!Auth::hasRole('owner', 'administrator')) {
        return hqForbidden('Only owners and administrators can view AI usage.');
    }
    $days = (int) ($_GET['days'] ?? 30);
    $days = in_array($days, [1, 7, 30, 90, 365], true) ? $days : 30;
    $report = AiUsage::report($days);

    if (($_GET['format'] ?? '') === 'csv') {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['day', 'calls', 'errors', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'estimated_calls', 'avg_latency_ms']);
        foreach ($report['by_day'] as $r) {
            fputcsv($out, [$r['day'], $r['calls'], $r['errors'], $r['prompt_tokens'], $r['completion_tokens'], $r['total_tokens'], $r['estimated_calls'], $r['avg_latency_ms']]);
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        return (new Response())
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="mova-ai-usage-' . $days . 'd.csv"')
            ->body($csv);
    }

    return renderHq('ai_usage/index', [
        'title'    => 'AI usage',
        'days'     => $days,
        'report'   => $report,
        'siteId'   => AiConfig::siteId(),
        'siteHost' => AiConfig::siteHost(),
        'gateway'  => AiConfig::usesPlatformGateway(),
        'endpoint' => AiConfig::get('ai_api_url'),
        'model'    => AiConfig::get('ai_model'),
        // Numbers the gateway itself recorded for this site (cached 5 min; ?refresh=1 forces).
        'gw'       => AiUsage::gatewayReport($days, isset($_GET['refresh'])),
        'gwCaps'   => \Mova\AI\AiCapabilities::get(),
    ]);
});
