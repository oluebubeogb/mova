<?php

declare(strict_types=1);

namespace MovaDevEditor\Editor;

use MovaDevEditor\Security\RoleGate;
use MovaDevEditor\Storage\MetaStorage;

class EditorRenderer
{
    private array $config;
    private string $basePath;
    private MetaStorage $storage;
    private RoleGate $gate;

    public function __construct(array $config, string $basePath, MetaStorage $storage, RoleGate $gate)
    {
        $this->config = $config;
        $this->basePath = $basePath;
        $this->storage = $storage;
        $this->gate = $gate;
    }

    public function render(array $context = []): void
    {
        if (!$this->gate->canUseDevMode()) {
            return;
        }

        $content = $context['content'] ?? [];
        $mode = $this->storage->mode($content);
        $css = $this->storage->css($content);
        $js = $this->storage->js($content);
        $useSiteChrome = $this->storage->useSiteChrome($content);
        $body = (string) ($content['body'] ?? '');
        $monaco = $this->config['monaco_cdn'] ?? 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs';
        $assetBase = '/mova-plugins/mova-dev-editor/assets';

        // Prefer plugin-served assets via a simple public path convention;
        // fall back to reading CSS inline if the web server does not map /mova-plugins.
        $cssFile = $this->basePath . '/assets/css/dev-editor.css';
        $jsFile  = $this->basePath . '/assets/js/editor-boot.js';

        include $this->basePath . '/views/editor-panels.php';
    }
}
