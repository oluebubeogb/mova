<?php

declare(strict_types=1);

namespace MovaDevEditor\Render;

use MovaDevEditor\Storage\MetaStorage;

class FrontendInjector
{
    private array $config;
    private MetaStorage $storage;
    private CssScoper $scoper;
    private HtmlDocumentParser $parser;

    public function __construct(array $config, MetaStorage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->scoper = new CssScoper();
        $this->parser = new HtmlDocumentParser();
    }

    public function rootId(array $content): string
    {
        $id = (int) ($content['id'] ?? 0);
        return 'mova-dev-root-' . ($id > 0 ? $id : 'x');
    }

    /**
     * @return array{body: string, css: string, js: string, title: string, full: bool}
     */
    public function resolve(array $content): array
    {
        $body = (string) ($content['body'] ?? '');
        $css  = $this->storage->css($content);
        $js   = $this->storage->js($content);
        $title = '';
        $full = false;

        if ($this->parser->isFullDocument($body)) {
            $full = true;
            $parts = $this->parser->extract($body);
            $body = $parts['body'];
            $title = $parts['title'];
            $css = trim($parts['css'] . "\n" . $css);
            $js  = trim($parts['js'] . "\n" . $js);
        } else {
            $body = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body) ?? $body;
            $body = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $body) ?? $body;
            $body = preg_replace('#<link\b[^>]*rel\s*=\s*["\']?stylesheet["\']?[^>]*>#is', '', $body) ?? $body;
        }

        return [
            'body'  => $body,
            'css'   => $css,
            'js'    => $js,
            'title' => $title,
            'full'  => $full,
        ];
    }

    public function filterBody(string $body, array $content = []): string
    {
        if (!$this->storage->isDev($content)) {
            return $body;
        }

        $resolved = $this->resolve(array_merge($content, ['body' => $body]));
        $rid = $this->rootId($content);

        return '<div id="' . htmlspecialchars($rid) . '" class="mova-dev-isolate" data-mova-dev="1"'
            . ($resolved['full'] ? ' data-mova-full-doc="1"' : '')
            . '>'
            . $resolved['body']
            . '</div>';
    }

    public function renderHead(array $context = []): void
    {
        $content = $context['content'] ?? null;
        if (!$content || !$this->storage->isDev($content)) {
            return;
        }
        if (!empty($this->config['disable_all_custom_css'])) {
            return;
        }

        $resolved = $this->resolve($content);
        $css = trim($resolved['css']);
        if ($css === '') {
            return;
        }

        $scope = '#' . $this->rootId($content);
        $scoped = $this->scoper->scope($css, $scope);
        $scoped = str_replace('</style>', '<\/style>', $scoped);

        echo "\n<!-- Mova Dev Editor CSS (scoped to {$scope}) -->\n";
        echo "<style id=\"mova-dev-css\">\n";
        echo $scope . " { isolation: isolate; position: relative; }\n";
        echo $scoped . "\n</style>\n";
    }

    public function renderFooter(array $context = []): void
    {
        $content = $context['content'] ?? null;
        if (!$content || !$this->storage->isDev($content)) {
            return;
        }
        if (!empty($this->config['disable_all_custom_js'])) {
            return;
        }

        $resolved = $this->resolve($content);
        $js = trim($resolved['js']);
        if ($js === '') {
            return;
        }

        $rid = $this->rootId($content);
        $js = str_replace('</script>', '<\/script>', $js);

        echo "\n<!-- Mova Dev Editor JS -->\n<script id=\"mova-dev-js\">\n";
        echo "(function(){\n";
        echo "  function movaDevRun(){\n";
        echo "    var root = document.getElementById(" . json_encode($rid) . ");\n";
        echo "    if (!root) { console.warn('[Mova Dev Editor] root not found', " . json_encode($rid) . "); return; }\n";
        echo "    try {\n";
        echo $js . "\n";
        echo "    } catch (e) {\n";
        echo "      console.error('[Mova Dev Editor]', e);\n";
        echo "    }\n";
        echo "  }\n";
        echo "  if (document.readyState === 'loading') {\n";
        echo "    document.addEventListener('DOMContentLoaded', movaDevRun);\n";
        echo "  } else {\n";
        echo "    movaDevRun();\n";
        echo "  }\n";
        echo "})();\n";
        echo "</script>\n";
    }
}
