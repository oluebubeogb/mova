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

Bootstrap::init();

$request = new Request();
$path = $request->path();

// Installation check
if (!Schema::isInstalled()) {
    // Remove host index.html etc. so DirectoryIndex / visitors reach Mova
    PlaceholderCleanup::neutralize();
    if (strpos($path, '/hq') !== 0) {
        header('Location: /hq/install');
        exit;
    }
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

// Page cache for anonymous GET (skip search & HQ)
$cacheable = $request->isGet()
    && !Auth::check()
    && $path !== '/search'
    && strpos($path, '/hq') !== 0;

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

$router->get('/robots.txt', function () {
    $body = "User-agent: *\nAllow: /\nDisallow: /hq/\n\nSitemap: " . Bootstrap::baseUrl() . "/sitemap.xml\n";
    return (new Response())->header('Content-Type', 'text/plain')->body($body);
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

// Homepage — optional fixed content from Brand → Identity
$router->get('/', function () use ($contentRepo, $seo) {
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
                'title'   => $content['title'] ?? null,
            ]);
        }
    }
    $posts = $contentRepo->published(10);
    return renderTheme('home', [
        'posts' => $posts,
        'seo'   => $seo,
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

$response = $router->dispatch($request);

// Cache successful HTML page responses
if ($cacheable && $response->getStatus() >= 200 && $response->getStatus() < 400) {
    $body = $response->getBody();
    // Only cache full HTML documents (skip plain robots etc. is fine too)
    if ($body !== '') {
        PageCache::put($cacheKey, $body);
    }
}

$response->send();

// --- Theme helper ---
function renderTheme(string $view, array $data = [], int $status = 200): Response
{
    $mgr = new \Mova\Theme\ThemeManager();
    $themePath = $mgr->path();
    $file = $themePath . '/' . $view . '.php';
    if (!file_exists($file)) {
        $themePath = Bootstrap::path('themes') . '/default';
        $file = $themePath . '/' . $view . '.php';
    }
    if (!file_exists($file)) {
        return Response::make("View not found: {$view}", 500);
    }

    $data['view'] = $view;
    $data['themePath'] = $themePath;
    $data['activeTheme'] = $mgr->activeSlug();
    extract($data);
    ob_start();
    include $themePath . '/layout.php';
    $html = ob_get_clean();

    return (new Response())->status($status)->body($html);
}


/**
 * Responsive featured/media image for themes (lazy + srcset 480/768/1200).
 */
function mova_img(string $src, string $alt = '', array $attrs = []): string
{
    return \Mova\Media\ImageTag::html($src, $alt, $attrs);
}
