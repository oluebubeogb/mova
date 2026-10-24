<?php

declare(strict_types=1);

use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Security\Csrf;
use Mova\Security\Audit;
use Mova\Content\ContentRepository;
use Mova\Auth\Auth;
use Mova\Theme\VariableService;
use Mova\Theme\DesignConfig;

/** @var \Mova\Core\Router $router */

/**
 * Studio — Phase 1
 * Separate multi-column workspace (structure / style panels / code / preview).
 */

// ── List / picker ────────────────────────────────────────────────────────────
$router->get('/studio', function (Request $req) {
    requireAuth();
    $repo = new ContentRepository();
    $q = trim((string) $req->query('q', ''));
    $status = (string) $req->query('status', '');

    $filters = [];
    if ($status !== '' && $status !== 'all') {
        $filters['status'] = $status;
    }

    // Prefer search when a query is present
    if ($q !== '') {
        $items = $repo->search($q, 40);
    } else {
        $items = $repo->all($filters, 40, 0);
    }

    // Attach meta (editor_mode) for display badges
    foreach ($items as &$it) {
        $meta = $repo->getMeta((int) $it['id']);
        $it['editor_mode'] = $meta['editor_mode'] ?? 'visual';
    }
    unset($it);

    return renderHq('studio/index', [
        'items'  => $items,
        'q'      => $q,
        'status' => $status,
        'title'  => 'Studio',
    ]);
});

// ── Editor for one content item ──────────────────────────────────────────────
$router->get('/studio/{id}', function (Request $req, array $params) {
    requireAuth();
    $id = (int) ($params['id'] ?? 0);
    $repo = new ContentRepository();
    $content = $repo->find($id);
    if (!$content) {
        return (new Response())->redirect('/hq/studio');
    }

    $meta = $repo->getMeta($id);
    $body = (string) ($content['body'] ?? '');
    $css  = (string) ($meta['raw_css'] ?? '');
    $js   = (string) ($meta['raw_js'] ?? '');

    // Site design variables for preview parity with public site
    $siteCssVars = '';
    $varMap = [];
    try {
        $siteCssVars = \Mova\Theme\DesignConfig::cssVariables();
    } catch (\Throwable $e) {
        $siteCssVars = '';
    }
    try {
        $varMap = \Mova\Theme\VariableService::map();
    } catch (\Throwable $e) {
        $varMap = [];
    }
    // Ensure every VariableService entry is also a CSS custom property in preview
    try {
        $extra = [':root {'];
        foreach (\Mova\Theme\VariableService::all() as $row) {
            $name = (string) ($row['name'] ?? '');
            $val = (string) ($row['value'] ?? '');
            if ($name === '') {
                continue;
            }
            $cssName = \Mova\Theme\VariableService::toCssName($name);
            $extra[] = '  ' . $cssName . ': ' . \Mova\Theme\VariableService::cssSafe($val) . ';';
        }
        $extra[] = '}';
        $siteCssVars .= "\n" . implode("\n", $extra);
    } catch (\Throwable $e) {
        // ignore
    }

    // Phase 5: the preview iframe must render with the real public stylesheets
    // + the active theme's CSS, otherwise mx-* sections look unstyled.
    $previewStyles = [];
    $previewThemeCss = '';
    $previewBodyClass = 'mx-site view-page';
    try {
        $ver = \Mova\Theme\SiteFrame::assetVersion();
        foreach (['/assets/fonts/fonts.css', '/assets/css/mova.css', '/assets/css/templates.css', '/assets/css/site.css', '/assets/css/sections.css'] as $u) {
            $previewStyles[] = $u . '?v=' . rawurlencode($ver);
        }
        $previewStyles[] = '/assets/vendor/fontawesome/css/all.min.css?v=6.5.2';
        $tm = new \Mova\Theme\ThemeManager();
        $slug = preg_replace('/[^a-z0-9_-]/i', '', $tm->activeSlug()) ?: 'default';
        $dir = $tm->themesPath() . '/' . $slug;
        $cssFile = is_file($dir . '/' . $slug . '.css') ? $dir . '/' . $slug . '.css' : (glob($dir . '/*.css')[0] ?? '');
        if ($cssFile !== '' && is_file($cssFile)) {
            $previewThemeCss = (string) file_get_contents($cssFile);
        }
        if (class_exists(\Mova\Theme\ElementStyles::class)) {
            $previewThemeCss .= "\n" . \Mova\Theme\ElementStyles::compileCss();
        }
        $previewBodyClass = 'theme-' . strtolower($slug) . ' mx-site view-page';
    } catch (\Throwable $e) {
        // preview falls back to bare tokens
    }

    return renderHq('studio/editor', [
        'previewStyles'    => $previewStyles,
        'previewThemeCss'  => $previewThemeCss,
        'previewBodyClass' => $previewBodyClass,
        'content'     => $content,
        'body'        => $body,
        'css'         => $css,
        'js'          => $js,
        'meta'        => $meta,
        'siteCssVars' => $siteCssVars,
        'varMap'      => $varMap,
        'title'       => 'Studio — ' . ($content['title'] ?? 'Untitled'),
    ]);
});

// ── AJAX save (no full page reload) ──────────────────────────────────────────
$router->post('/studio/{id}/save', function (Request $req, array $params) {
    requireAuth();
    if ($__deny = requireContentAccess((int) ($params['id'] ?? 0))) { return $__deny; }
    if (!Csrf::validate()) {
        return (new Response())
            ->status(403)
            ->header('Content-Type', 'application/json')
            ->body(json_encode(['ok' => false, 'message' => 'CSRF token invalid']));
    }

    $id = (int) ($params['id'] ?? 0);
    $repo = new ContentRepository();
    $existing = $repo->find($id);
    if (!$existing) {
        return (new Response())
            ->status(404)
            ->header('Content-Type', 'application/json')
            ->body(json_encode(['ok' => false, 'message' => 'Content not found']));
    }

    $body  = (string) $req->post('body', '');
    $css   = (string) $req->post('raw_css', '');
    $js    = (string) $req->post('raw_js', '');
    $title = trim((string) $req->post('title', $existing['title'] ?? ''));

    // Keep title if blank post
    if ($title === '') {
        $title = (string) ($existing['title'] ?? 'Untitled');
    }

    $data = [
        'title' => $title,
        'body'  => $body,
        // Preserve status — Studio Phase 1 does not change publish state
        'status' => $existing['status'] ?? 'draft',
    ];

    $repo->update($id, $data);

    $meta = $repo->getMeta($id);
    $meta['editor_mode'] = 'studio';
    $meta['raw_css'] = $css;
    $meta['raw_js']  = $js;
    $repo->saveMeta($id, $meta);

    try {
        Audit::log('studio.saved', 'content', $id, [
            'title' => $title,
            'user'  => Auth::id(),
        ]);
    } catch (\Throwable $e) {
        // audit is best-effort
    }

    return (new Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode([
            'ok'      => true,
            'message' => 'Saved',
            'id'      => $id,
            'updated' => date('c'),
        ]));
});

// ── New blank content opened directly in Studio ──────────────────────────────
$router->post('/studio/new', function (Request $req) {
    requireAuth();
    if ($__deny = requireContentAccess(null)) { return $__deny; }
    if (!Csrf::validate()) {
        return (new Response())->status(403)->body('CSRF');
    }

    $repo = new ContentRepository();
    $title = trim((string) $req->post('title', 'Untitled'));
    if ($title === '') {
        $title = 'Untitled';
    }

    $slug = $repo->generateSlug($title);
    $id = $repo->create([
        'title'  => $title,
        'slug'   => $slug,
        'body'   => '',
        'status' => 'draft',
        'type'   => 'page',
        'author_id' => Auth::id(),
    ]);

    $repo->saveMeta($id, [
        'editor_mode' => 'studio',
        'raw_css'     => '',
        'raw_js'      => '',
    ]);

    return (new Response())->redirect('/hq/studio/' . $id);
});
