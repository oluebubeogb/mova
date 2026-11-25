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
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
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
                // HQ: inline scripts for editors. Monaco, CodeMirror, Font Awesome and
                // fonts are bundled under /assets (Phase 5), so no third-party CDNs.
                $frames = self::cspFrameSources();
                $csp = "default-src 'self'; "
                    . "script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com; "
                    . "style-src 'self' 'unsafe-inline'; "
                    . "font-src 'self' data:; "
                    . "img-src 'self' data: https: blob:; "
                    . "connect-src 'self' https://cloudflareinsights.com; "
                    . "worker-src 'self' blob:; "
                    . "child-src " . $frames . " blob:; "
                    . "frame-src " . $frames . "; "
                    . "frame-ancestors 'self'; "
                    . "base-uri 'self'; "
                    . "form-action 'self'";
			} else {
                // Public site: Font Awesome is bundled locally; Cloudflare Insights allowed; rest tight.
                // frame-src / child-src allow common embeds (Maps, YouTube, OSM); see security.csp_frame_src.
                $frames = self::cspFrameSources();
                $csp = "default-src 'self'; "
                    . "script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com; "
                    . "style-src 'self' 'unsafe-inline'; "
                    . "font-src 'self' data:; "
                    . "img-src 'self' data: https: blob:; "
                    . "connect-src 'self' https://cloudflareinsights.com; "
                    . "child-src " . $frames . "; "
                    . "frame-src " . $frames . "; "
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


    /**
     * CSP frame-src / child-src host list.
     * Defaults (Maps, YouTube, OSM) come from config security.csp_frame_src;
     * extra hosts can be appended with env MOVA_CSP_FRAME_SRC (space-separated).
     */
    private static function cspFrameSources(): string
    {
        $defaults = [
            "'self'",
            'https://www.google.com',
            'https://maps.google.com',
            'https://*.google.com',
            'https://www.youtube.com',
            'https://www.youtube-nocookie.com',
            'https://youtube.com',
            'https://www.openstreetmap.org',
        ];
        $sources = $defaults;
        if (class_exists(Bootstrap::class)) {
            $configured = Bootstrap::config('security.csp_frame_src', $defaults);
            if (is_string($configured)) {
                $configured = preg_split('/\s+/', trim($configured)) ?: [];
            }
            if (is_array($configured) && $configured !== []) {
                $sources = $configured;
            }
        }
        $extra = trim((string) (getenv('MOVA_CSP_FRAME_SRC') ?: ''));
        if ($extra !== '') {
            $sources = array_merge($sources, preg_split('/\s+/', $extra) ?: []);
        }

        $out = [];
        foreach ($sources as $src) {
            $src = trim((string) $src);
            if ($src === 'self' || $src === "'self'") {
                $out["'self'"] = "'self'";
                continue;
            }
            if (preg_match('#^https://(\*\.)?[A-Za-z0-9.-]+(?::\d+)?$#', $src)) {
                $out[$src] = $src;
            }
        }
        if (!isset($out["'self'"])) {
            $out = ["'self'" => "'self'"] + $out;
        }

        return implode(' ', $out);
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

