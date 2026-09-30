<?php
/**
 * Conservative Markdown → HTML for Mova content bodies.
 *
 * Designed for HTML-first storage: only convert when the body looks like
 * Markdown and is not already structured HTML. Safe subset only — no raw
 * HTML pass-through from untrusted MD source.
 */

declare(strict_types=1);

namespace Mova\Content;

final class Markdown
{
    /**
     * Convert Markdown to HTML when appropriate; otherwise return unchanged.
     */
    public static function maybeToHtml(string $body): string
    {
        if ($body === '' || !self::shouldParse($body)) {
            return $body;
        }

        try {
            return self::toHtml($body);
        } catch (\Throwable $e) {
            return $body;
        }
    }

    /**
     * True when body is primarily Markdown (not structured HTML).
     */
    public static function shouldParse(string $body): bool
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return false;
        }

        // Structured HTML fragments / full pages — leave alone (Dev/Studio/visual editor)
        if (preg_match(
            '/<(p|div|h[1-6]|ul|ol|li|table|thead|tbody|tr|td|th|article|section|header|footer|nav|main|figure|figcaption|blockquote|pre|form|style|script|iframe|svg|video|audio|canvas|details|summary|aside|mova-|assembly)\b/i',
            $trimmed
        )) {
            return false;
        }

        // Markdown signals
        return (bool) preg_match(
            '/(\*\*[^*]+\*\*|__[^_\s][^_]*__|(?<!\*)\*(?!\*)([^*\n]+)\*(?!\*)|(?<!_)_(?!_)([^_\n]+)_(?!_)|^#{1,6}\s+\S|^\s*[-*+]\s+\S|^\s*\d+\.\s+\S|`[^`\n]+`|\[[^\]]+\]\([^)\s]+\)|^>\s+\S)/m',
            $trimmed
        );
    }

    /**
     * Convert a Markdown string to safe HTML (subset).
     */
    public static function toHtml(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $md = trim($md);
        if ($md === '') {
            return '';
        }

        $codeBlocks = [];
        $md = preg_replace_callback(
            '/```([a-zA-Z0-9_-]*)\n?([\s\S]*?)```/',
            static function (array $m) use (&$codeBlocks): string {
                $idx = count($codeBlocks);
                $code = rtrim($m[2], "\n");
                $codeBlocks[$idx] = '<pre><code>' . self::escape($code) . '</code></pre>';
                return "\n\n%%CODEBLOCK{$idx}%%\n\n";
            },
            $md
        ) ?? $md;

        $lines = explode("\n", $md);
        $out = [];
        $para = [];
        $listType = null; // 'ul' | 'ol'
        $listItems = [];

        $flushPara = static function () use (&$para, &$out): void {
            if ($para === []) {
                return;
            }
            $text = trim(implode("\n", $para));
            $para = [];
            if ($text === '') {
                return;
            }
            $out[] = '<p>' . self::inline($text) . '</p>';
        };

        $flushList = static function () use (&$listType, &$listItems, &$out): void {
            if ($listType === null || $listItems === []) {
                $listType = null;
                $listItems = [];
                return;
            }
            $tag = $listType;
            $html = '<' . $tag . '>';
            foreach ($listItems as $item) {
                $html .= '<li>' . self::inline($item) . '</li>';
            }
            $html .= '</' . $tag . '>';
            $out[] = $html;
            $listType = null;
            $listItems = [];
        };

        foreach ($lines as $line) {
            // Code block placeholders (own block)
            if (preg_match('/^%%CODEBLOCK(\d+)%%$/', trim($line), $m)) {
                $flushPara();
                $flushList();
                $out[] = $codeBlocks[(int) $m[1]] ?? '';
                continue;
            }

            // Empty line → break paragraph / list
            if (trim($line) === '') {
                $flushPara();
                $flushList();
                continue;
            }

            // Headings
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
                $flushPara();
                $flushList();
                $level = strlen($m[1]);
                $out[] = '<h' . $level . '>' . self::inline(trim($m[2])) . '</h' . $level . '>';
                continue;
            }

            // Blockquote
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $flushPara();
                $flushList();
                $out[] = '<blockquote><p>' . self::inline(trim($m[1])) . '</p></blockquote>';
                continue;
            }

            // Unordered list
            if (preg_match('/^\s*([-*+])\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($listType !== null && $listType !== 'ul') {
                    $flushList();
                }
                $listType = 'ul';
                $listItems[] = $m[2];
                continue;
            }

            // Ordered list
            if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $m)) {
                $flushPara();
                if ($listType !== null && $listType !== 'ol') {
                    $flushList();
                }
                $listType = 'ol';
                $listItems[] = $m[1];
                continue;
            }

            // Horizontal rule
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})\s*$/', trim($line))) {
                $flushPara();
                $flushList();
                $out[] = '<hr>';
                continue;
            }

            // Continuation of list item (indented)
            if ($listType !== null && preg_match('/^\s{2,}(.+)$/', $line, $m)) {
                $last = count($listItems) - 1;
                if ($last >= 0) {
                    $listItems[$last] .= ' ' . $m[1];
                }
                continue;
            }

            // Normal paragraph line
            $flushList();
            $para[] = $line;
        }

        $flushPara();
        $flushList();

        $html = implode("\n", $out);

        // Restore any leftover placeholders (should not happen)
        $html = preg_replace_callback(
            '/%%CODEBLOCK(\d+)%%/',
            static function (array $m) use ($codeBlocks): string {
                return $codeBlocks[(int) $m[1]] ?? '';
            },
            $html
        ) ?? $html;

        return $html;
    }

    private static function inline(string $text): string
    {
        // Escape first, then re-introduce safe tags from MD syntax
        $text = self::escape($text);

        // Inline code
        $text = preg_replace_callback(
            '/`([^`]+)`/',
            static function (array $m): string {
                return '<code>' . $m[1] . '</code>';
            },
            $text
        ) ?? $text;

        // Images ![alt](url)
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/',
            static function (array $m): string {
                $src = self::safeUrl($m[2]);
                if ($src === null) {
                    return $m[0];
                }
                return '<img src="' . self::escape($src) . '" alt="' . $m[1] . '" loading="lazy">';
            },
            $text
        ) ?? $text;

        // Links [text](url)
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/',
            static function (array $m): string {
                $href = self::safeUrl($m[2]);
                if ($href === null) {
                    return $m[0];
                }
                return '<a href="' . self::escape($href) . '">' . $m[1] . '</a>';
            },
            $text
        ) ?? $text;

        // Bold ** or __
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text) ?? $text;

        // Italic * or _ (avoid matching inside words for _)
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![\w])_([^_]+)_(?![\w])/', '<em>$1</em>', $text) ?? $text;

        // Soft line breaks within a paragraph
        $text = str_replace("\n", "<br>\n", $text);

        return $text;
    }

    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Allow http(s), mailto, relative paths, anchors. Block javascript: etc.
     */
    private static function safeUrl(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return null;
        }
        // Protocol-relative or absolute with scheme
        if (preg_match('~^(https?:|mailto:|/|\./|\.\./|#)~i', $url)) {
            if (preg_match('~^(javascript|data|vbscript):~i', $url)) {
                return null;
            }
            return $url;
        }
        // Bare path or slug-like
        if (preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._~:/?\#\[\]@!$&\'()*+,;=%-]*$~', $url)
            && !preg_match('~^(javascript|data|vbscript):~i', $url)
        ) {
            return $url;
        }
        return null;
    }

    /**
     * Register content.render.body filter (runs before shortcode expansion).
     */
    public static function registerRenderFilter(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!class_exists(\Mova\Plugin\PluginManager::class)) {
            return;
        }

        // Priority 5: before Assembly shortcodes (20)
        \Mova\Plugin\PluginManager::addFilter(
            'content.render.body',
            static function (string $body, array $content = []) {
                return self::maybeToHtml($body);
            },
            5
        );
    }
}
