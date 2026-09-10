<?php
/**
 * Mova HQ — Administration front controller (thin bootstrap)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Bootstrap.php';
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
    'assembly',
    'media',
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
    'ai',
    'health',
    'sites',
    'plugins',
    'integrations',
    'security_center',
    'backups',
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

$response = $router->dispatch($fakeRequest);
$response->send();
