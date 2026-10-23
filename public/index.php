<?php
/**
 * Mova CMS - Public Front Controller
 * Content that moves.
 *
 * Works when this file is in /public (standard) or at project root (shared hosting).
 */

declare(strict_types=1);

$movaBootstrap = dirname(__DIR__) . '/app/Core/Bootstrap.php';
if (!is_file($movaBootstrap)) {
    // Flat / public_html layout: index.php sits next to /app
    $movaBootstrap = __DIR__ . '/app/Core/Bootstrap.php';
}
require $movaBootstrap;

use Mova\Core\Bootstrap;
use Mova\Core\Request;
use Mova\Core\Response;
use Mova\Core\Router;
use Mova\Core\Schema;
use Mova\Core\Database;
use Mova\Content\ContentRepository;
use Mova\SEO\SeoService;
use Mova\Cache\PageCache;
use Mova\Auth\Auth;
use Mova\Core\PlaceholderCleanup;
use Mova\Core\MovaManifest;

Bootstrap::init();

$request = new Request();
$path = $request->path();

// REST API (/api/v1) — stateless, API-key authenticated. Dispatched before the page
// router so the catch-all /{parent}/{child} route can never swallow it.
if (\Mova\Api\RestApi::handles($path)) {
    if (!Schema::isInstalled()) {
        (new Response())->json(['error' => ['code' => 'not_installed', 'message' => 'Mova is not installed yet.']], 503)->send();
        exit;
    }
    (new \Mova\Api\RestApi())->dispatch($request, $path)->send();
    exit;
}

// Installation check
if (!Schema::isInstalled()) {
    // Remove host index.html etc. so DirectoryIndex / visitors reach Mova
    PlaceholderCleanup::neutralize();
    if (strpos($path, '/hq') !== 0) {
        header('Location: /hq/install');
        exit;
    }
}

// PWA endpoints (/manifest.webmanifest, /sw.js, /pwa-offline, /pwa-icon-*) — served by PHP so they
// work on shared hosting with no build step. /sw.js is always answered (a kill-switch worker
// when the PWA is disabled) so a previously installed worker unregisters itself cleanly.
if (\Mova\Site\Pwa::handles($path)) {
    \Mova\Site\Pwa::serve($path);
}
// Signed-in visitors get personalised HTML: tell the PWA service worker never to cache it.
try {
    if (Auth::check()) {
        header('X-Mova-Private: 1');
    }
} catch (\Throwable $e) {
}

$contentRepo = new ContentRepository();

// Publish any due scheduled content (lightweight, on-request)
try {
    $published = $contentRepo->publishScheduled();
    if ($published > 0) {
        PageCache::flush();
    }
} catch (\Throwable $e) {
    // ignore
}

// Preview overrides (HQ theme gallery, Quick setup review, bin/ mockup capture).
// Honoured only for signed-in HQ users or a valid signed URL; never cached.
$__previewKeys = array_intersect_key($_GET, array_flip(['mova_theme']));
if ($__previewKeys) {
    $__previewOk = false;
    try {
        $__previewOk = Auth::check() || \Mova\Theme\ThemeManager::verifySignedQuery($_GET);
    } catch (\Throwable $e) {
    }
    if ($__previewOk) {
        if (!empty($_GET['mova_theme'])) {
            \Mova\Theme\ThemeManager::setPreview((string) $_GET['mova_theme']);
        }
        header('X-Robots-Tag: noindex, nofollow');
    }
}

// Page cache for anonymous GET (skip search, HQ, and system XML/text endpoints)
// System routes must not be cached as bare body — they need correct Content-Type
$systemPaths = ['/favicon.svg', '/favicon.ico', '/search', '/gallery', '/feedback', '/blog', '/robots.txt', '/sitemap.xml', '/feed.xml', '/llms.txt', '/unsubscribe', '/mova.json'];
$cacheable = $request->isGet()
    && !Auth::check()
    && !$__previewKeys
    && !isset($_GET['mx_static'])
    && !in_array($path, $systemPaths, true)
    && strpos($path, '/hq') !== 0
    && strpos($path, '/_preview') !== 0
    && strpos($path, '/gallery') !== 0;

$cacheKey = 'page_' . md5($path . '?' . ($_SERVER['QUERY_STRING'] ?? ''));

if ($cacheable) {
    $cached = PageCache::get($cacheKey);
    if ($cached !== null) {
        header('X-Mova-Cache: HIT');
        header('Cache-Control: public, max-age=60, s-maxage=3600');
        echo $cached;
        exit;
    }
}

$router = new Router();
$seo = new SeoService();

// --- System routes ---

// Default favicon: site initial on the brand gradient (used when no favicon is uploaded)
$router->get('/favicon.svg', function () {
    $name = 'Mova';
    $p = '#2563eb';
    $a = '#7c3aed';
    try {
        $name = (string) \Mova\Theme\DesignConfig::setting('site_name', 'Mova');
        $t = \Mova\Theme\DesignConfig::tokens();
        $p = (string) ($t['colors']['primary'] ?? $p);
        $a = (string) ($t['colors']['accent'] ?? $a);
    } catch (\Throwable $e) {
    }
    $ok = static fn($c, $d) => preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : $d;
    $p = $ok($p, '#2563eb');
    $a = $ok($a, '#7c3aed');
    $ch = htmlspecialchars(mb_strtoupper(mb_substr(trim($name) ?: 'M', 0, 1)), ENT_XML1);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . $p . '"/><stop offset="1" stop-color="' . $a . '"/></linearGradient></defs>'
        . '<rect width="64" height="64" rx="16" fill="url(#g)"/>'
        . '<text x="32" y="43" text-anchor="middle" font-family="Inter,Segoe UI,Helvetica,Arial,sans-serif" font-size="34" font-weight="800" fill="#fff">' . $ch . '</text></svg>';
    return (new Response())->header('Content-Type', 'image/svg+xml; charset=utf-8')
        ->header('Cache-Control', 'public, max-age=3600')->body($svg);
});
$router->get('/favicon.ico', function () {
    $fav = '';
    try {
        $fav = (string) \Mova\Theme\DesignConfig::setting('favicon_url', '');
    } catch (\Throwable $e) {
    }
    return (new Response())->status(302)->header('Location', $fav !== '' ? $fav : '/favicon.svg')->body('');
});

$router->get('/robots.txt', function () {
    $body = "User-agent: *\nAllow: /\nDisallow: /hq/\n\nSitemap: " . Bootstrap::baseUrl() . "/sitemap.xml\n";
    return (new Response())->header('Content-Type', 'text/plain')->body($body);
});


$router->get('/mova.json', function () {
    return (new Response())
        ->header('Content-Type', 'application/json; charset=utf-8')
        ->header('Cache-Control', 'public, max-age=60')
        ->body(MovaManifest::toJson());
});


$router->get('/sitemap.xml', function () use ($seo) {
    return (new Response())
        ->header('Content-Type', 'application/xml; charset=utf-8')
        ->body($seo->generateSitemap());
});

$router->get('/feed.xml', function () use ($seo) {
    return (new Response())
        ->header('Content-Type', 'application/rss+xml; charset=utf-8')
        ->body($seo->generateFeed());
});

$router->get('/llms.txt', function () use ($seo) {
    return (new Response())
        ->header('Content-Type', 'text/plain; charset=utf-8')
        ->body($seo->generateLlmsTxt());
});

$router->get('/unsubscribe', function (Request $req) {
    $token = trim((string) $req->query('token', ''));
    $ok = false;
    if ($token !== '') {
        try {
            $ok = (new \Mova\Mail\MailService())->unsubscribe($token);
        } catch (\Throwable $e) {
            $ok = false;
        }
    }
    $msg = $ok
        ? 'You have been unsubscribed.'
        : 'Invalid or expired unsubscribe link.';
    return Response::make(
        '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Unsubscribe</title></head>'
        . '<body style="font-family:system-ui;max-width:32rem;margin:4rem auto;padding:0 1rem;">'
        . '<h1>Mova</h1><p>' . htmlspecialchars($msg) . '</p>'
        . '<p><a href="/">Back to site</a></p></body></html>'
    );
});

$router->get('/search', function (Request $req) use ($contentRepo, $seo) {
    $q = trim((string) $req->query('q', ''));
    $results = $q ? $contentRepo->search($q, 30) : [];
    return renderTheme('search', [
        'query'   => $q,
        'results' => $results,
        'seo'     => $seo,
        'title'   => $q ? "Search: {$q}" : 'Search',
    ]);
});


$router->get('/gallery', function (Request $req) use ($seo) {
    $svc = new \Mova\Gallery\GalleryService();
    $q = trim((string) $req->query('q', ''));
    $sidebar = $svc->all(true, 50, 0);
    $recent = $svc->recent(4, true);
    $stream = $svc->streamImages(48, 72, null);
    $searchResults = $q !== '' ? $svc->search($q, 40) : null;
    return renderTheme('gallery', [
        'seo' => $seo,
        'title' => $q !== '' ? ('Gallery search: ' . $q) : 'Gallery',
        'mode' => $q !== '' ? 'search' : 'explore',
        'query' => $q,
        'sidebar' => $sidebar,
        'recent' => $recent,
        'stream' => $stream,
        'gallery' => null,
        'items' => [],
        'searchResults' => $searchResults,
    ]);
});

$router->get('/gallery/api/stream', function (Request $req) {
    $svc = new \Mova\Gallery\GalleryService();
    $before = trim((string) $req->query('before', ''));
    $stream = $svc->streamImages(48, 72, $before !== '' ? $before : null);
    return (new Response())
        ->header('Content-Type', 'application/json; charset=utf-8')
        ->body(json_encode($stream));
});

$router->get('/gallery/api/search', function (Request $req) {
    $svc = new \Mova\Gallery\GalleryService();
    $q = trim((string) $req->query('q', ''));
    $results = $svc->search($q, 40);
    return (new Response())
        ->header('Content-Type', 'application/json; charset=utf-8')
        ->body(json_encode($results));
});


$router->get('/gallery/api/embed/{id}', function (Request $req, array $params) {
    $id = (int) ($params['id'] ?? 0);
    $svc = new \Mova\Gallery\GalleryService();
    $layout = $req->query('layout');
    $preset = $req->query('preset');
    $opts = [];
    $optsRaw = $req->query('opts');
    if (is_string($optsRaw) && $optsRaw !== '') {
        $decoded = json_decode($optsRaw, true);
        if (is_array($decoded)) {
            $opts = $decoded;
        }
    }
    $payload = $svc->embedPayload(
        $id,
        $layout ? (string) $layout : null,
        $opts,
        $preset ? (string) $preset : null
    );
    if (!$payload) {
        return (new \Mova\Core\Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['error' => 'Not found']));
    }
    // Only published on public
    $g = $svc->find($id);
    if (!$g || ($g['status'] ?? '') !== 'published') {
        return (new \Mova\Core\Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['error' => 'Not found']));
    }
    return (new \Mova\Core\Response())
        ->header('Content-Type', 'application/json')
        ->header('Cache-Control', 'public, max-age=60')
        ->body(json_encode($payload));
});

$router->get('/feedback/api/form/{slug}', function (Request $req, array $params) {
    $slug = (string) ($params['slug'] ?? '');
    $svc = new \Mova\Feedback\FeedbackService();
    $form = $svc->findBySlug($slug, true);
    if (!$form) {
        return (new \Mova\Core\Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['error' => 'Not found']));
    }
    return (new \Mova\Core\Response())
        ->header('Content-Type', 'application/json')
        ->body(json_encode([
            'form' => [
                'id' => $form['id'],
                'title' => $form['title'],
                'slug' => $form['slug'],
                'description' => $form['description'] ?? '',
                'success_message' => $form['success_message'] ?? '',
                'fields' => $form['fields'],
            ],
        ]));
});

$router->post('/feedback/api/submit/{slug}', function (Request $req, array $params) {
    $slug = (string) ($params['slug'] ?? '');
    $svc = new \Mova\Feedback\FeedbackService();
    $form = $svc->findBySlug($slug, true);
    if (!$form) {
        return (new \Mova\Core\Response())
            ->header('Content-Type', 'application/json')
            ->status(404)
            ->body(json_encode(['success' => false, 'error' => 'Form not found']));
    }
    $raw = file_get_contents('php://input');
    $json = $raw ? json_decode($raw, true) : null;
    $values = is_array($json['values'] ?? null) ? $json['values'] : ($req->post() ?: []);
    try {
        $svc->submit((int) $form['id'], $values, [
            'page_url' => is_array($json) ? ($json['page_url'] ?? null) : null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        return (new \Mova\Core\Response())
            ->header('Content-Type', 'application/json')
            ->body(json_encode([
                'success' => true,
                'message' => $form['success_message'] ?? 'Thank you.',
            ]));
    } catch (\Throwable $e) {
        return (new \Mova\Core\Response())
            ->header('Content-Type', 'application/json')
            ->status(400)
            ->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
});



// —— Newsletter subscribe (section library form) ——
$router->post('/subscribe', function (Request $req) {
    $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'json');
    $reply = function (bool $ok, string $msg, int $status = 200) use ($wantsJson) {
        if ($wantsJson) {
            return (new \Mova\Core\Response())->header('Content-Type', 'application/json')->status($status)
                ->body(json_encode(['ok' => $ok, 'message' => $msg]));
        }
        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $bp = parse_url($back);
        if (!empty($bp['host']) && $bp['host'] !== $host) { $back = '/'; }
        $back = ($bp['path'] ?? '/') ?: '/';
        return (new \Mova\Core\Response())->status(303)->header('Location', $back . '?subscribed=' . ($ok ? '1' : '0'));
    };
    if (trim((string) $req->post('website', '')) !== '') {
        return $reply(true, 'Thanks — you’re subscribed!'); // honeypot: silently accept
    }
    $ip = \Mova\Security\ClientIp::get();
    try {
        if (!(new \Mova\Api\RateLimiter())->hit(null, $ip, 'public:subscribe', 6, 600)) {
            return $reply(false, 'Too many attempts — please try again later.', 429);
        }
    } catch (\Throwable $e) { /* limiter table missing: continue */ }
    $email = trim((string) $req->post('email', ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        return $reply(false, 'Please enter a valid email address.', 422);
    }
    try {
        (new \Mova\Mail\MailService())->addSubscriber($email, null);
        return $reply(true, 'Thanks — you’re subscribed!');
    } catch (\Throwable $e) {
        return $reply(false, 'Could not subscribe right now — please try again later.', 500);
    }
});

// —— Blogs ——
$router->get('/blog', function (Request $req) use ($seo) {
    $svc = new \Mova\Blog\BlogService();
    $blog = $svc->findBySlug('blog', true);
    if (!$blog) {
        return renderTheme('404', ['seo' => $seo], 404);
    }
    $limit = (int) ($blog['per_page'] ?? 12);
    $feed = $svc->feed((int) $blog['id'], $limit, 0);
    $html = $svc->renderFeedHtml($blog, $feed['items']);
    return renderTheme('blog', [
        'seo' => $seo,
        'title' => $blog['title'],
        'blog' => $blog,
        'feed' => $feed,
        'html' => $html,
    ]);
});

$router->get('/blog/api/feed/{id}', function (Request $req, array $params) {
    $svc = new \Mova\Blog\BlogService();
    $id = (int) ($params['id'] ?? 0);
    $blog = $svc->find($id);
    if (!$blog || ($blog['status'] ?? '') !== 'published') {
        return (new Response())->header('Content-Type', 'application/json')->status(404)
            ->body(json_encode(['error' => 'Not found']));
    }
    $limit = (int) $req->query('limit', $blog['per_page'] ?? 12);
    $offset = (int) $req->query('offset', 0);
    $feed = $svc->feed($id, $limit, $offset);
    $html = $svc->renderFeedHtml($blog, $feed['items']);
    return (new Response())->header('Content-Type', 'application/json')
        ->body(json_encode([
            'html' => $html,
            'items' => $feed['items'],
            'has_more' => $feed['has_more'],
            'next_offset' => $feed['next_offset'],
            'total' => $feed['total'],
            'limit' => $limit,
        ]));
});

$router->get('/blog/{slug}', function (Request $req, array $params) use ($seo) {
    $svc = new \Mova\Blog\BlogService();
    $slug = (string) ($params['slug'] ?? '');
    if ($slug === '' || $slug === 'api') {
        return renderTheme('404', ['seo' => $seo], 404);
    }
    $blog = $svc->findBySlug($slug, true);
    if (!$blog) {
        return renderTheme('404', ['seo' => $seo], 404);
    }
    $limit = (int) ($blog['per_page'] ?? 12);
    $feed = $svc->feed((int) $blog['id'], $limit, 0);
    $html = $svc->renderFeedHtml($blog, $feed['items']);
    return renderTheme('blog', [
        'seo' => $seo,
        'title' => $blog['title'],
        'blog' => $blog,
        'feed' => $feed,
        'html' => $html,
    ]);
});

$router->get('/gallery/{slug}', function (Request $req, array $params) use ($seo) {
    $svc = new \Mova\Gallery\GalleryService();
    $slug = $params['slug'] ?? '';
    $gallery = $svc->findBySlug($slug, true);
    if ($gallery) {
        $items = $svc->items((int) $gallery['id']);
        // Stay in group context when this gallery belongs to a group
        $gSlug = trim((string) ($gallery['group_slug'] ?? ''));
        if ($gSlug !== '') {
            $sidebar = $svc->byGroup($gSlug, true, 100, 0);
            $groupSlug = $gSlug;
        } else {
            $sidebar = $svc->all(true, 50, 0);
            $groupSlug = null;
        }
        return renderTheme('gallery', [
            'seo' => $seo,
            'title' => $gallery['title'],
            'mode' => 'gallery',
            'query' => '',
            'sidebar' => $sidebar,
            'recent' => [],
            'stream' => ['items' => [], 'has_more' => false, 'next_before' => null],
            'gallery' => $gallery,
            'items' => $items,
            'searchResults' => null,
            'groupSlug' => $groupSlug,
        ]);
    }

    // Treat as gallery group: list member galleries under /gallery/{group_slug}
    $groupMembers = $svc->byGroup($slug, true, 100, 0);
    if ($groupMembers) {
        $sidebar = $groupMembers;
        $title = ucwords(str_replace('-', ' ', $slug));
        return renderTheme('gallery', [
            'seo' => $seo,
            'title' => $title,
            'mode' => 'group',
            'query' => '',
            'sidebar' => $sidebar,
            'recent' => array_slice($groupMembers, 0, 4),
            'stream' => ['items' => [], 'has_more' => false, 'next_before' => null],
            'gallery' => null,
            'items' => [],
            'searchResults' => null,
            'groupSlug' => $slug,
        ]);
    }

    return renderTheme('404', ['seo' => $seo], 404);
});


// Homepage — optional fixed content from Brand → Identity
// Browser title is always "Sitename | site description" on the homepage.

// --- Previews (section thumbnails, kit demos, Quick setup review). Never cached, never indexed. ---
$__wizLoad = dirname(__DIR__) . '/mova-plugins/mova-setup-wizard/src/load.php';
$__previewFinish = static function (Response $res): Response {
    $html = preg_replace('~<link[^>]+site-ai/widget\.css[^>]*>|<div id="mova-site-ai-root"[^>]*></div>|<script[^>]+site-ai/widget\.js[^>]*></script>~', '', $res->getBody()) ?? $res->getBody();
    $html = preg_replace(['~<meta name="robots"[^>]*>~', '~<link rel="canonical"[^>]*>\s*~'], ['<meta name="robots" content="noindex, nofollow">', ''], $html) ?? $html;
    return $res->body($html)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store');
};
$__previewTheme = static function (): void {
    $t = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($_GET['theme'] ?? ''))) ?? '';
    if ($t !== '') {
        \Mova\Theme\ThemeManager::setPreview($t);
    }
};
$router->get('/_preview/section/{id}', function (Request $req, array $params) use ($seo, $__previewFinish, $__previewTheme) {
    $entry = \Mova\Theme\SectionLibrary::find((string) ($params['id'] ?? ''));
    if (!$entry) {
        return Response::make('Not Found', 404);
    }
    $__previewTheme();
    \Mova\Theme\SiteFrame::$chrome = false;
    $style = in_array($_GET['style'] ?? '', ['classic', 'modern', 'compact'], true) ? $_GET['style'] : 'modern';
    $body = \Mova\Theme\Sections::page([$entry['spec']], $style);
    return $__previewFinish(renderTheme('content', [
        'content' => ['id' => 0, 'title' => $entry['name'], 'slug' => '_preview', 'type' => 'page', 'status' => 'published', 'body' => $body, 'excerpt' => '', 'published_at' => date('c'), 'updated_at' => date('c'), 'meta' => ['page_layout' => 'canvas']],
        'seo' => $seo, 'title' => $entry['name'] . ' — section preview',
    ]));
});
$router->get('/_preview/kit/{kit}/{page}', function (Request $req, array $params) use ($seo, $__wizLoad, $__previewFinish, $__previewTheme) {
    if (!is_file($__wizLoad)) {
        return Response::make('Not Found', 404);
    }
    require_once $__wizLoad;
    $kit = \MovaSetupWizard\KitRepository::load((string) ($params['kit'] ?? ''));
    if (!$kit || empty($kit['pages'])) {
        return Response::make('Not Found', 404);
    }
    $demo = (array) ($kit['demo'] ?? []);
    // Optional live brand overrides (colours/fonts only — harmless, nothing persisted)
    $demo = \MovaSetupWizard\WizardDraft::merge($demo + ['font_pair' => 'modern'], array_intersect_key($_GET, array_flip(['primary', 'accent', 'secondary', 'font_pair'])));
    $demo['theme'] = '';
    $__previewTheme();
    $q = http_build_query(array_intersect_key($_GET, array_flip(['theme', 'primary', 'accent', 'secondary', 'font_pair', 'mx_static'])));
    $base = '/_preview/kit/' . $kit['id'];
    $prep = \MovaSetupWizard\PreviewRenderer::prepare($kit, (string) $params['page'], $demo, $base, $q);
    if (!$prep) {
        return Response::make('Not Found', 404);
    }
    $res = renderTheme('content', ['content' => $prep['content'], 'seo' => $seo, 'title' => $prep['title']]);
    $res->body(\MovaSetupWizard\PreviewRenderer::rewriteLinks($res->getBody(), $prep['slugs'], $base, $q));
    return $__previewFinish($res);
});
$router->get('/_preview/wizard/{page}', function (Request $req, array $params) use ($seo, $__wizLoad, $__previewFinish) {
    if (!Auth::check() || !is_file($__wizLoad)) {
        return Response::make('Sign in to preview', 403);
    }
    require_once $__wizLoad;
    $d = \MovaSetupWizard\WizardDraft::load((int) Auth::id());
    $kitId = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['kit'] ?? '')) ?: (string) ($d['kit'] ?? '');
    $kit = \MovaSetupWizard\KitRepository::load($kitId);
    if (!$kit) {
        return Response::make('Choose a kit first', 404);
    }
    if (!empty($_GET['theme'])) {
        $d['theme'] = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $_GET['theme'])) ?? '';
    }
    $q = http_build_query(array_intersect_key($_GET, array_flip(['theme', 'kit'])));
    $base = '/_preview/wizard';
    $prep = \MovaSetupWizard\PreviewRenderer::prepare($kit, (string) $params['page'], $d, $base, $q);
    if (!$prep) {
        return Response::make('Not Found', 404);
    }
    $res = renderTheme('content', ['content' => $prep['content'], 'seo' => $seo, 'title' => $prep['title']]);
    $res->body(\MovaSetupWizard\PreviewRenderer::rewriteLinks($res->getBody(), $prep['slugs'], $base, $q));
    return $__previewFinish($res);
});

$router->get('/', function () use ($contentRepo, $seo) {
    $siteName = 'Mova';
    $siteDesc = '';
    try {
        if (class_exists(\Mova\Theme\DesignConfig::class)) {
            $siteName = (string) \Mova\Theme\DesignConfig::setting('site_name', Bootstrap::config('app_name', 'Mova'));
            $siteDesc = (string) \Mova\Theme\DesignConfig::setting('site_description', Bootstrap::config('app_tagline', ''));
        }
    } catch (\Throwable $e) {}
    $homeTitle = $siteDesc !== '' ? ($siteName . ' | ' . $siteDesc) : $siteName;

    $homeId = 0;
    try {
        if (class_exists(\Mova\Theme\DesignConfig::class)) {
            $homeId = (int) \Mova\Theme\DesignConfig::setting('homepage_content_id', '0');
        }
    } catch (\Throwable $e) {}
    if ($homeId > 0) {
        $content = $contentRepo->find($homeId);
        if ($content && ($content['status'] ?? '') === 'published') {
            return renderTheme('content', [
                'content' => $content,
                'seo'     => $seo,
                'title'   => $homeTitle,
            ]);
        }
    }
    $posts = $contentRepo->published(10);
    return renderTheme('home', [
        'posts' => $posts,
        'seo'   => $seo,
        'title' => $homeTitle,
    ]);
});

// Content by slug
$router->get('/{slug}', function (Request $req, array $params) use ($contentRepo, $seo) {
    $slug = $params['slug'] ?? '';

    $reserved = Bootstrap::config('reserved_routes', []);
    if (in_array(strtolower($slug), array_map('strtolower', $reserved), true)) {
        return Response::make('Not Found', 404);
    }

    $content = $contentRepo->findBySlug($slug);
    if (!$content) {
        return renderTheme('404', ['seo' => $seo], 404);
    }

    try {
        Database::insert('page_views', [
            'content_id' => $content['id'],
            'path'       => '/' . $slug,
            'referrer'   => $_SERVER['HTTP_REFERER'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            'ip_hash'    => hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . date('Y-m-d')),
            'viewed_at'  => date('c'),
        ]);
    } catch (\Throwable $e) {
        // ignore analytics errors
    }

    return renderTheme('content', [
        'content' => $content,
        'seo'     => $seo,
    ]);
});

// Hierarchical (simple support: /parent/child)
$router->get('/{parent}/{child}', function (Request $req, array $params) use ($contentRepo, $seo) {
    $slug = $params['child'] ?? '';
    $content = $contentRepo->findBySlug($slug);
    if (!$content) {
        return renderTheme('404', ['seo' => $seo], 404);
    }
    return renderTheme('content', [
        'content' => $content,
        'seo'     => $seo,
    ]);
});

// Mova Site AI public API (plugin) — chat, sessions mirror, feedback
if (is_file(dirname(__DIR__) . '/mova-plugins/mova-site-ai/src/SiteAiService.php')) {
    require_once dirname(__DIR__) . '/mova-plugins/mova-site-ai/src/KnowledgeBank.php';
    require_once dirname(__DIR__) . '/mova-plugins/mova-site-ai/src/SiteAiService.php';
    require_once dirname(__DIR__) . '/mova-plugins/mova-site-ai/src/SiteAiSessions.php';

    /**
     * Per-IP throttle for the public Site AI API (each chat call costs a model request).
     * Returns a 429 Response when over the limit, null otherwise.
     */
    $siteAiThrottle = static function (string $bucket, int $perMinute, int $perDay): ?Response {
        try {
            $ip = \Mova\Security\ClientIp::get();
            $rl = new \Mova\Api\RateLimiter();
            $okMin = $rl->hit(null, $ip, 'site-ai:' . $bucket . ':m', $perMinute, 60);
            $okDay = $rl->hit(null, $ip, 'site-ai:' . $bucket . ':d', $perDay, 86400);
            if (!$okMin || !$okDay) {
                return (new Response())
                    ->header('Retry-After', $okMin ? '3600' : '60')
                    ->json(['error' => 'Too many requests — please wait a moment and try again.'], 429);
            }
        } catch (\Throwable $e) {
            // limiter table missing → fail open rather than break the widget
        }
        return null;
    };

    $router->post('/api/site-ai/chat', function (Request $req) use ($siteAiThrottle) {
        $msg = trim((string) ($req->post('message') ?? ''));
        if (mb_strlen($msg) > 2000) {
            return (new Response())->json(['error' => 'Message is too long (max 2000 characters).'], 413);
        }
        if ($blocked = $siteAiThrottle('chat', 12, 300)) {
            return $blocked;
        }
        $page = mb_substr((string) ($req->post('page') ?? '/'), 0, 500);
        $pageTitle = mb_substr((string) ($req->post('page_title') ?? ''), 0, 300);
        $sessionId = (int) ($req->post('session_id') ?? 0) ?: null;
        $clientId = trim((string) ($req->post('client_id') ?? ''));
        $visitorIn = trim((string) ($req->post('visitor_id') ?? ''));
        if ($msg === '') {
            return (new Response())->json(['error' => 'Empty message'], 400);
        }
        $cfg = \MovaSiteAi\SiteAiService::config();
        if (empty($cfg['enabled'])) {
            return (new Response())->json(['error' => 'Site AI disabled'], 403);
        }
        $vid = \MovaSiteAi\SiteAiSessions::resolveVisitorId($visitorIn !== '' ? $visitorIn : null);
        // Persist user message
        $appended = \MovaSiteAi\SiteAiSessions::appendMessage(
            $vid,
            $sessionId,
            'user',
            $msg,
            null,
            $clientId !== '' ? $clientId : null,
            $msg
        );
        $sessionId = $appended['session_id'];
        $result = \MovaSiteAi\SiteAiService::chat($msg, $page, $pageTitle);
        // Persist assistant reply
        \MovaSiteAi\SiteAiSessions::appendMessage(
            $vid,
            $sessionId,
            'assistant',
            (string) ($result['reply'] ?? ''),
            $result['links'] ?? [],
            $clientId !== '' ? $clientId : null
        );
        return (new Response())->json([
            'ok' => true,
            'visitor_id' => $vid,
            'session_id' => $sessionId,
        ] + $result);
    });

    $router->get('/api/site-ai/sessions', function (Request $req) {
        $cfg = \MovaSiteAi\SiteAiService::config();
        if (empty($cfg['enabled'])) {
            return (new Response())->json(['error' => 'Site AI disabled'], 403);
        }
        $vid = \MovaSiteAi\SiteAiSessions::resolveVisitorId(
            trim((string) ($req->query('visitor_id') ?? ''))
        );
        $sessions = \MovaSiteAi\SiteAiSessions::listSessions($vid);
        return (new Response())->json(['ok' => true, 'visitor_id' => $vid, 'sessions' => $sessions]);
    });

    $router->get('/api/site-ai/sessions/{id}', function (Request $req, array $params = []) {
        $cfg = \MovaSiteAi\SiteAiService::config();
        if (empty($cfg['enabled'])) {
            return (new Response())->json(['error' => 'Site AI disabled'], 403);
        }
        $vid = \MovaSiteAi\SiteAiSessions::resolveVisitorId(
            trim((string) ($req->query('visitor_id') ?? ''))
        );
        $id = (int) ($params['id'] ?? 0);
        $session = \MovaSiteAi\SiteAiSessions::getSession($id, $vid);
        if (!$session) {
            return (new Response())->json(['error' => 'Not found'], 404);
        }
        $messages = \MovaSiteAi\SiteAiSessions::listMessages($id, $vid);
        return (new Response())->json([
            'ok' => true,
            'visitor_id' => $vid,
            'session' => $session,
            'messages' => $messages,
        ]);
    });

    $router->post('/api/site-ai/sessions', function (Request $req) use ($siteAiThrottle) {
        if ($blocked = $siteAiThrottle('session', 20, 400)) {
            return $blocked;
        }
        $cfg = \MovaSiteAi\SiteAiService::config();
        if (empty($cfg['enabled'])) {
            return (new Response())->json(['error' => 'Site AI disabled'], 403);
        }
        $vid = \MovaSiteAi\SiteAiSessions::resolveVisitorId(
            trim((string) ($req->post('visitor_id') ?? ''))
        );
        $title = mb_substr(trim((string) ($req->post('title') ?? 'New chat')), 0, 120);
        $clientId = mb_substr(trim((string) ($req->post('client_id') ?? '')), 0, 64);
        $session = \MovaSiteAi\SiteAiSessions::createSession(
            $vid,
            $title !== '' ? $title : 'New chat',
            $clientId !== '' ? $clientId : null
        );
        return (new Response())->json(['ok' => true, 'visitor_id' => $vid, 'session' => $session]);
    });

    $router->post('/api/site-ai/sessions/{id}/delete', function (Request $req, array $params = []) {
        $cfg = \MovaSiteAi\SiteAiService::config();
        if (empty($cfg['enabled'])) {
            return (new Response())->json(['error' => 'Site AI disabled'], 403);
        }
        $vid = \MovaSiteAi\SiteAiSessions::resolveVisitorId(
            trim((string) ($req->post('visitor_id') ?? ''))
        );
        $id = (int) ($params['id'] ?? 0);
        $ok = \MovaSiteAi\SiteAiSessions::deleteSession($id, $vid);
        return (new Response())->json(['ok' => $ok, 'visitor_id' => $vid]);
    });

    $router->post('/api/site-ai/feedback', function (Request $req) use ($siteAiThrottle) {
        if ($blocked = $siteAiThrottle('feedback', 30, 600)) {
            return $blocked;
        }
        $cfg = \MovaSiteAi\SiteAiService::config();
        if (empty($cfg['enabled'])) {
            return (new Response())->json(['error' => 'Site AI disabled'], 403);
        }
        $vid = \MovaSiteAi\SiteAiSessions::resolveVisitorId(
            trim((string) ($req->post('visitor_id') ?? ''))
        );
        $rating = (string) ($req->post('rating') ?? 'up');
        $sessionId = (string) ($req->post('session_id') ?? '');
        $hash = (string) ($req->post('message_hash') ?? '');
        $page = (string) ($req->post('page') ?? '');
        \MovaSiteAi\SiteAiSessions::saveFeedback($vid, $rating, $sessionId ?: null, $hash ?: null, $page ?: null);
        return (new Response())->json(['ok' => true, 'visitor_id' => $vid]);
    });
}

$response = $router->dispatch($request);

// Cache successful HTML page responses
if ($cacheable && $response->getStatus() >= 200 && $response->getStatus() < 400) {
    $body = $response->getBody();
    // Only cache full HTML documents (skip plain robots etc. is fine too)
    if ($body !== '') {
        PageCache::put($cacheKey, $body);
    }
}

// Theme preview (?mova_theme=…): keep the preview theme while the visitor clicks around inside the
// preview iframe, and strip the site-AI widget. Only reached for signed-in users / signed URLs.
if (!empty($__previewOk) && \Mova\Theme\ThemeManager::previewSlug() !== null) {
    $__pb = $response->getBody();
    if ($__pb !== '' && stripos($__pb, '</body>') !== false) {
        $__pq = json_encode(http_build_query(array_intersect_key($_GET, array_flip(['mova_theme', 'mova_exp', 'mova_sig', 'mx_static']))));
        $__ps = '<script>(function(q){document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href]");if(!a||a.target==="_blank")return;var u=new URL(a.href,location.href);if(u.origin!==location.origin||/^\\/(hq|assets|media)\\//.test(u.pathname))return;if(!u.searchParams.has("mova_theme")){u.search+=(u.search?"&":"?")+q;a.href=u.toString();}},true);document.addEventListener("submit",function(e){var f=e.target;if(f.method&&f.method.toLowerCase()!=="get")return;new URLSearchParams(q).forEach(function(v,k){var i=document.createElement("input");i.type="hidden";i.name=k;i.value=v;f.appendChild(i);});},true);})(' . $__pq . ');</script>';
        $__pb = preg_replace('~<link[^>]+site-ai/widget\.css[^>]*>|<div id="mova-site-ai-root"[^>]*></div>|<script[^>]+site-ai/widget\.js[^>]*></script>~', '', $__pb) ?? $__pb;
        $response->body(preg_replace('~</body>~i', $__ps . '</body>', $__pb, 1) ?? $__pb)->header('Cache-Control', 'no-store');
    }
}

$response->send();

// --- Theme helper ---

/**
 * Inject public Site AI widget when enabled (works even if plugin hook missed).
 */
function mova_inject_site_ai_widget(string $html): string
{
    $svc = dirname(__DIR__) . '/mova-plugins/mova-site-ai/src/SiteAiService.php';
    $kb = dirname(__DIR__) . '/mova-plugins/mova-site-ai/src/KnowledgeBank.php';
    if (!is_file($svc) || !is_file($kb)) {
        return $html;
    }
    require_once $kb;
    require_once $svc;
    try {
        $cfg = \MovaSiteAi\SiteAiService::config();
    } catch (\Throwable $e) {
        return $html;
    }
    if (empty($cfg['enabled'])) {
        return $html;
    }
    if (str_contains($html, 'mova-site-ai-root')) {
        return $html;
    }
    $name = htmlspecialchars((string) ($cfg['name'] ?? 'Assistant'), ENT_QUOTES, 'UTF-8');
    $primary = htmlspecialchars((string) ($cfg['primary'] ?? ''), ENT_QUOTES, 'UTF-8');
    $accent = htmlspecialchars((string) ($cfg['accent'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bgLight = htmlspecialchars((string) ($cfg['bg_light'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bgDark = htmlspecialchars((string) ($cfg['bg_dark'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cssFile = (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') : '') . '/assets/site-ai/widget.css';
    $jsFile = (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') : '') . '/assets/site-ai/widget.js';
    $cssVer = is_file($cssFile) ? (string) filemtime($cssFile) : (string) time();
    $jsVer = is_file($jsFile) ? (string) filemtime($jsFile) : (string) time();
    // Fallback when DOCUMENT_ROOT is not public/
    if ($cssVer === (string) time()) {
        $alt = dirname(__DIR__) . '/public/assets/site-ai/widget.css';
        if (is_file($alt)) {
            $cssVer = (string) filemtime($alt);
        }
        $altJs = dirname(__DIR__) . '/public/assets/site-ai/widget.js';
        if (is_file($altJs)) {
            $jsVer = (string) filemtime($altJs);
        }
    }
    $snippet = '<link rel="stylesheet" href="/assets/site-ai/widget.css?v=' . $cssVer . '">'
        . '<div id="mova-site-ai-root" data-name="' . $name . '"'
        . ($primary !== '' ? ' data-primary="' . $primary . '"' : '')
        . ($accent !== '' ? ' data-accent="' . $accent . '"' : '')
        . ($bgLight !== '' ? ' data-bg-light="' . $bgLight . '"' : '')
        . ($bgDark !== '' ? ' data-bg-dark="' . $bgDark . '"' : '')
        . ' data-api="/api/site-ai/chat"></div>'
        . '<script src="/assets/site-ai/widget.js?v=' . $jsVer . '" defer></script>';
    if (stripos($html, '</body>') !== false) {
        return preg_replace('/<\/body>/i', $snippet . '</body>', $html, 1) ?? ($html . $snippet);
    }
    return $html . $snippet;
}

function renderTheme(string $view, array $data = [], int $status = 200): Response
{
    $mgr = new \Mova\Theme\ThemeManager();
    $themePath = $mgr->path();
    $file = $themePath . '/' . $view . '.php';
    if (!file_exists($file)) {
        // View missing in the active theme: borrow the default theme's view, keep the active layout.
        $file = $mgr->themesPath() . '/default/' . $view . '.php';
    }
    if (!file_exists($file) || !file_exists($themePath . '/layout.php')) {
        return Response::make("View not found: {$view}", 500);
    }

    $data['view'] = $view;
    $data['viewFile'] = $file;
    $data['themePath'] = $themePath;
    $data['activeTheme'] = $mgr->activeSlug();
    extract($data);
    ob_start();
    include $themePath . '/layout.php';
    $html = ob_get_clean();
    $html = mova_inject_site_ai_widget($html);

    return (new Response())->status($status)->body($html);
}


/**
 * Responsive featured/media image for themes (lazy + srcset 480/768/1200).
 */
function mova_img(string $src, string $alt = '', array $attrs = []): string
{
    return \Mova\Media\ImageTag::html($src, $alt, $attrs);
}
