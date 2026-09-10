<?php

declare(strict_types=1);

namespace MovaDevEditor\Render;

/**
 * Detects a full standalone HTML document and splits it into
 * body markup, CSS, and JS so Dev Mode can render it correctly.
 */
class HtmlDocumentParser
{
    public function isFullDocument(string $html): bool
    {
        $trim = ltrim($html);
        if ($trim === '') {
            return false;
        }
        if (preg_match('/^<!DOCTYPE\s+html/i', $trim)) {
            return true;
        }
        if (preg_match('/^<html\b/i', $trim)) {
            return true;
        }
        // head + body present is also a strong signal
        if (preg_match('/<head\b/i', $html) && preg_match('/<body\b/i', $html)) {
            return true;
        }
        return false;
    }

    /**
     * @return array{body: string, css: string, js: string, title: string}
     */
    public function extract(string $html): array
    {
        $cssParts = [];
        $jsParts = [];
        $title = '';

        // <title>
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        // <style>…</style>
        if (preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $html, $m)) {
            foreach ($m[1] as $block) {
                $cssParts[] = trim($block);
            }
        }

        // <link rel="stylesheet" href="..."> — keep as @import so relative URLs still work when possible
        if (preg_match_all('#<link\b[^>]*>#is', $html, $m)) {
            foreach ($m[0] as $tag) {
                if (!preg_match('/rel\s*=\s*["\']?stylesheet["\']?/i', $tag)) {
                    continue;
                }
                if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/i', $tag, $hm)) {
                    $href = trim($hm[1]);
                    if ($href !== '') {
                        $cssParts[] = '@import url(' . $href . ');';
                    }
                }
            }
        }

        // Inline <script>…</script> (skip external src-only scripts for safety; optional include src as note)
        if (preg_match_all('#<script\b([^>]*)>(.*?)</script>#is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $attrs = $row[1];
                $code = trim($row[2]);
                if (preg_match('/\bsrc\s*=/i', $attrs)) {
                    // External script — inject as dynamic script tag string in JS so it can load
                    if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $attrs, $sm)) {
                        $src = $sm[1];
                        $jsParts[] = '(function(){var s=document.createElement("script");s.src='
                            . json_encode($src)
                            . ';document.head.appendChild(s);})();';
                    }
                    continue;
                }
                if ($code !== '') {
                    $jsParts[] = $code;
                }
            }
        }

        // Body inner HTML
        $body = $html;
        if (preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $m)) {
            $body = $m[1];
        } else {
            // Strip document chrome if present but no body match
            $body = preg_replace('#<!DOCTYPE[^>]*>#i', '', $body) ?? $body;
            $body = preg_replace('#</?(html|head|body)\b[^>]*>#i', '', $body) ?? $body;
            $body = preg_replace('#<title\b[^>]*>.*?</title>#is', '', $body) ?? $body;
        }

        // Remove style/script/link from body fragment (already extracted)
        $body = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $body) ?? $body;
        $body = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $body) ?? $body;
        $body = preg_replace('#<link\b[^>]*rel\s*=\s*["\']?stylesheet["\']?[^>]*>#is', '', $body) ?? $body;

        return [
            'body'  => trim($body),
            'css'   => implode("\n\n", array_filter($cssParts)),
            'js'    => implode("\n\n", array_filter($jsParts)),
            'title' => $title,
        ];
    }
}
