<?php
/**
 * DevFallback — renders Dev Mode pages sensibly when the Mova Dev Editor plugin is not active
 * (for example right after importing a site pack onto a site where the plugin is off).
 *
 * Without it, a page saved as a full HTML document (<!DOCTYPE html><html><head>…) would be
 * echoed inside the site's own <body>, and the page's raw CSS/JS (stored in content meta)
 * would not load at all. Here we keep the document's <style> blocks, same-origin stylesheets
 * and scripts, unwrap its <body>, and add the page's raw_css / raw_js — wrapped in one
 * container so the result is valid inside the theme layout.
 */

declare(strict_types=1);

namespace Mova\Theme;

final class DevFallback
{
    public static function isFullDocument(string $html): bool
    {
        $t = ltrim($html);
        return (bool) (preg_match('/^(?:<!--.*?-->\s*)*<!DOCTYPE\s+html/is', $t)
            || preg_match('/^<html\b/i', $t)
            || (preg_match('/<head\b/i', $html) && preg_match('/<body\b/i', $html)));
    }

    /** True when the dev plugin already wrapped the body (it is active). */
    public static function handled(string $body): bool
    {
        return str_contains($body, 'data-mova-dev="1"');
    }

    public static function render(array $content, string $body): string
    {
        $css = [];
        $js = [];
        $links = [];
        if (self::isFullDocument($body)) {
            if (preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $body, $m)) {
                foreach ($m[1] as $c) {
                    $css[] = $c;
                }
            }
            if (preg_match_all('#<link\b[^>]*>#is', $body, $m)) {
                foreach ($m[0] as $tag) {
                    // Only same-origin stylesheets: third-party ones are blocked by the CSP anyway.
                    if (preg_match('/rel\s*=\s*["\']?stylesheet/i', $tag) && preg_match('/href\s*=\s*["\'](\/(?!\/)[^"\']*)["\']/i', $tag, $hm)) {
                        $links[] = '<link rel="stylesheet" href="' . htmlspecialchars($hm[1], ENT_QUOTES, 'UTF-8') . '">';
                    }
                }
            }
            if (preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $body, $m, PREG_SET_ORDER)) {
                foreach ($m as $s) {
                    if (preg_match('/\bsrc\s*=\s*["\'](\/(?!\/)[^"\']*)["\']/i', $s[1], $sm)) {
                        $js[] = ['src' => $sm[1]];
                    } elseif (!preg_match('/\bsrc\s*=/i', $s[1]) && !preg_match('/type\s*=\s*["\']?(?!text\/javascript|module)[a-z]+\/[a-z+-]+/i', $s[1])) {
                        $js[] = ['code' => $s[2]];
                    }
                }
            }
            $inner = preg_match('#<body\b[^>]*>(.*)</body>#is', $body, $bm) ? $bm[1] : (preg_replace('#^.*?<body\b[^>]*>#is', '', $body) ?? $body);
            $inner = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $inner) ?? $inner;
            $inner = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $inner) ?? $inner;
            $body = $inner;
        }
        $raw = trim((string) ($content['meta']['raw_css'] ?? ''));
        if ($raw !== '') {
            $css[] = $raw;
        }
        $rawJs = trim((string) ($content['meta']['raw_js'] ?? ''));
        if ($rawJs !== '') {
            $js[] = ['code' => $rawJs];
        }
        $id = 'mova-dev-root-' . (int) ($content['id'] ?? 0);
        $out = '<div id="' . $id . '" class="mova-dev-fallback" data-mova-dev-fallback="1">';
        $out .= implode('', $links);
        if ($css) {
            $out .= '<style>' . str_ireplace('</style', '<\/style', implode("\n", $css)) . '</style>';
        }
        $out .= $body;
        foreach ($js as $j) {
            if (isset($j['src'])) {
                $out .= '<script src="' . htmlspecialchars($j['src'], ENT_QUOTES, 'UTF-8') . '" defer></script>';
            } else {
                $code = str_ireplace('</script', '<\/script', (string) $j['code']);
                $out .= '<script>(function(){var root=document.getElementById(' . json_encode($id) . ');function run(){try{' . "\n" . $code . "\n" . '}catch(e){console.error("[Mova dev page]",e);}}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}})();</script>';
            }
        }
        return $out . '</div>';
    }
}
