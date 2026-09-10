<?php

declare(strict_types=1);

namespace MovaDevEditor\Storage;

class MetaStorage
{
    public function mode(array $content): string
    {
        $meta = $content['meta'] ?? [];
        $mode = $meta['editor_mode'] ?? 'visual';
        return $mode === 'dev' ? 'dev' : 'visual';
    }

    public function css(array $content): string
    {
        return (string) (($content['meta']['raw_css'] ?? '') ?: '');
    }

    public function js(array $content): string
    {
        return (string) (($content['meta']['raw_js'] ?? '') ?: '');
    }

    public function isDev(array $content): bool
    {
        return $this->mode($content) === 'dev';
    }

    /** Default off for dev pages */
    public function useSiteChrome(array $content): bool
    {
        return (($content['meta']['use_site_chrome'] ?? '0') === '1');
    }
}
