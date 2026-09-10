<?php
/**
 * Mova CMS - HTTP Request
 */

namespace Mova\Core;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $post;
    private array $server;
    private array $files;
    private array $cookies;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $_SERVER['REQUEST_URI'] ?? '/';
        $this->query   = $_GET ?? [];
        $this->post    = $_POST ?? [];
        $this->server  = $_SERVER ?? [];
        $this->files   = $_FILES ?? [];
        $this->cookies = $_COOKIE ?? [];

        $path = parse_url($this->uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $this->path = $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function segment(int $index): ?string
    {
        $segments = array_values(array_filter(explode('/', $this->path)));
        return $segments[$index] ?? null;
    }

    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path)));
    }

    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['HTTP_CLIENT_IP']
            ?? $this->server['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function isAjax(): bool
    {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    public function header(string $name, $default = null)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? $default;
    }
}
