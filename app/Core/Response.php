<?php
/**
 * Mova CMS - HTTP Response
 */

namespace Mova\Core;

class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function body(string $content): self
    {
        $this->body = $content;
        return $this;
    }

    public function json(array $data, int $status = 200): self
    {
        $this->status = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $this;
    }

    public function redirect(string $url, int $status = 302): self
    {
        $this->status = $status;
        $this->headers['Location'] = $url;
        $this->body = '';
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", true);
        }

        // Security headers
        if (!isset($this->headers['X-Content-Type-Options'])) {
            header('X-Content-Type-Options: nosniff');
        }
        if (!isset($this->headers['X-Frame-Options'])) {
            header('X-Frame-Options: SAMEORIGIN');
        }
        if (!isset($this->headers['Referrer-Policy'])) {
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
        if (!isset($this->headers['Permissions-Policy'])) {
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        }
        if (!isset($this->headers['Content-Security-Policy'])) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $isHq = (strpos($uri, '/hq') === 0);
            if ($isHq) {
                // HQ needs inline scripts for editors + optional Font Awesome CDN
                $csp = "default-src 'self'; "
                    . "script-src 'self' 'unsafe-inline'; "
                    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
                    . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; "
                    . "img-src 'self' data: https: blob:; "
                    . "connect-src 'self'; "
                    . "frame-ancestors 'self'; "
                    . "base-uri 'self'; "
                    . "form-action 'self'";
            } else {
                // Public site: tighter than HQ (no third-party fonts/CDNs; block plugins/objects)
                // unsafe-inline still needed for theme boot + optional inline element JS
                $csp = "default-src 'self'; "
                    . "script-src 'self' 'unsafe-inline'; "
                    . "style-src 'self' 'unsafe-inline'; "
                    . "font-src 'self' data:; "
                    . "img-src 'self' data: https: blob:; "
                    . "connect-src 'self'; "
                    . "frame-ancestors 'self'; "
                    . "base-uri 'self'; "
                    . "form-action 'self'; "
                    . "object-src 'none'; "
                    . "upgrade-insecure-requests";
            }
            header('Content-Security-Policy: ' . $csp);
        }

        // HSTS when request is HTTPS (complements .htaccess)
        if (!isset($this->headers['Strict-Transport-Security'])) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            if ($https) {
                header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
            }
        }

        echo $this->body;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public static function make(string $body = '', int $status = 200): self
    {
        return (new self())->status($status)->body($body);
    }
}

