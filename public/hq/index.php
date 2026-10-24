<?php
/**
 * Mova HQ — Administration front controller (thin bootstrap)
 */

declare(strict_types=1);

// Support both layouts:
// - Standard: public/hq/  →  app/ is two levels up
// - Flat:     hq/         →  app/ is one level up
$movaBootstrap = dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';
if (!is_file($movaBootstrap)) {
    $movaBootstrap = dirname(__DIR__) . '/app/Core/Bootstrap.php';
}
require $movaBootstrap;
require __DIR__ . '/helpers.php';

use Mova\Core\Bootstrap;
use Mova\Core\Request;
use Mova\Core\Router;

Bootstrap::init();

$request = new Request();
$path = $request->path();

// Strip /hq prefix for internal routing
$hqPath = $path;
if (strpos($path, '/hq') === 0) {
    $hqPath = substr($path, 3) ?: '/';
}

$router = new Router();

// Modular route registration
$routeFiles = [
    'install',
    'auth',
    'dashboard',
    'docs',
    'landings',
    'content',
    'studio',
    'assembly',
    'media',
    'seo',
    'galleries',
    'blogs',
    'feedback',
    'taxonomy',
    'types',
    'people',
    'mail',
    'mailbox',
    'sequences',
    'insights',
    'design',
    'settings',
    'api_keys',
    'webhooks',
    'pwa',
    'ai',
    'site_ai',
    'ai_usage',
    'search',
    'setup_wizard',
    'health',
    'sites',
    'plugins',
    'integrations',
    'security_center',
    'backups',
    'text_repair',
    'updates',
    'fallback',
];

foreach ($routeFiles as $file) {
    $routePath = __DIR__ . '/routes/' . $file . '.php';
    if (is_file($routePath)) {
        require $routePath;
    }
}

// Dispatch with adjusted path (strip /hq for internal match)
$fakeRequest = new class($hqPath, $request) extends Request {
    private string $forcedPath;
    private Request $orig;

    public function __construct(string $path, Request $orig)
    {
        $this->forcedPath = $path;
        $this->orig = $orig;
        parent::__construct();
    }

    public function path(): string
    {
        return $this->forcedPath;
    }

    public function method(): string
    {
        return $this->orig->method();
    }

    public function post(?string $key = null, $default = null)
    {
        return $this->orig->post($key, $default);
    }

    public function query(?string $key = null, $default = null)
    {
        return $this->orig->query($key, $default);
    }

    public function file(string $key): ?array
    {
        return $this->orig->file($key);
    }

    public function hasFile(string $key): bool
    {
        return $this->orig->hasFile($key);
    }
};

// Phase 5 — defence-in-depth role gate in front of every HQ route handler.
// 1) Read-only roles (viewer) can never mutate: any non-GET request is refused here, before
//    the handler runs, except the handful of endpoints that must work signed-out.
// 2) A signed-in user opening a registry page their role cannot use gets a 403 (handlers keep
//    their own checks; this only catches anything a handler forgot).
$gateResponse = (static function (string $hqPath, string $method): ?\Mova\Core\Response {
    if (!\Mova\Auth\Auth::check()) {
        return null; // handlers redirect to login themselves
    }
    $method = strtoupper($method);
    $openPosts = ['/login', '/logout', '/install', '/ai/worker/poke'];
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
        && !in_array(rtrim($hqPath, '/') ?: '/', $openPosts, true)
        && !\Mova\Auth\Auth::canMutate()) {
        return hqForbidden('Your role is read-only.');
    }
    if ($method === 'GET' || $method === 'HEAD') {
        try {
            $route = \Mova\Hq\RouteRegistry::match('/hq' . ($hqPath === '/' ? '' : $hqPath));
            if ($route && !\Mova\Hq\RouteRegistry::allowed($route['perm'] ?? null)) {
                return hqForbidden();
            }
        } catch (\Throwable $e) {
        }
    }
    return null;
})($hqPath, $request->method());

$response = $gateResponse ?? $router->dispatch($fakeRequest);
$response->send();
