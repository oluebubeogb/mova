<?php

declare(strict_types=1);

namespace MovaDevEditor\Render;

/**
 * Prefix CSS selectors so Dev Mode styles cannot affect site chrome or HQ.
 */
class CssScoper
{
    /**
     * When true, `@media (prefers-color-scheme: dark|light)` blocks follow the site's light/dark
     * toggle (html[data-theme]) instead of only the OS setting: the rules are emitted once for
     * html[data-theme="dark|light"] and kept inside the media query for html without data-theme
     * (no-JS visitors). This stops pages turning dark on a dark OS while the visitor chose light.
     */
    private bool $syncColorScheme;

    /** Extra qualifier for the html element (e.g. [data-theme="dark"]) while inside a synced block. */
    private string $ctx = '';

    public function __construct(bool $syncColorScheme = true)
    {
        $this->syncColorScheme = $syncColorScheme;
    }

    public function scope(string $css, string $scopeSelector): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        // Drop HTML comments / CSS comments (rough)
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        $out = '';
        $len = strlen($css);
        $i = 0;
        $buf = '';

        while ($i < $len) {
            // @rule
            if ($css[$i] === '@') {
                if ($buf !== '') {
                    $out .= $this->scopeRuleBlock($buf, $scopeSelector);
                    $buf = '';
                }
                $at = $this->readAtRule($css, $i);
                $out .= $this->scopeAtRule($at['text'], $scopeSelector);
                $i = $at['end'];
                continue;
            }
            $buf .= $css[$i];
            $i++;
        }
        if (trim($buf) !== '') {
            $out .= $this->scopeRuleBlock($buf, $scopeSelector);
        }

        return trim($out);
    }

    /** @return array{text: string, end: int} */
    private function readAtRule(string $css, int $start): array
    {
        $len = strlen($css);
        $i = $start;
        // read until { or ;
        while ($i < $len && $css[$i] !== '{' && $css[$i] !== ';') {
            $i++;
        }
        if ($i >= $len) {
            return ['text' => substr($css, $start), 'end' => $len];
        }
        if ($css[$i] === ';') {
            return ['text' => substr($css, $start, $i - $start + 1), 'end' => $i + 1];
        }
        // block: find matching }
        $depth = 0;
        $j = $i;
        while ($j < $len) {
            if ($css[$j] === '{') {
                $depth++;
            } elseif ($css[$j] === '}') {
                $depth--;
                if ($depth === 0) {
                    $j++;
                    break;
                }
            }
            $j++;
        }
        return ['text' => substr($css, $start, $j - $start), 'end' => $j];
    }

    private function scopeAtRule(string $at, string $scope): string
    {
        $at = trim($at);
        if ($at === '') {
            return '';
        }

        // @media, @supports, @document — re-scope inner
        if (preg_match('/^@(media|supports|layer|container)\b/i', $at)) {
            $brace = strpos($at, '{');
            if ($brace === false) {
                return $at . "\n";
            }
            $header = substr($at, 0, $brace + 1);
            $inner = substr($at, $brace + 1);
            if (str_ends_with($inner, '}')) {
                $inner = substr($inner, 0, -1);
            }
            if ($this->syncColorScheme && $this->ctx === ''
                && preg_match('/^@media\b(?!.*\bnot\b)(.*)\(\s*prefers-color-scheme\s*:\s*(dark|light)\s*\)/is', substr($at, 0, $brace), $cm)) {
                $mode = strtolower($cm[2]);
                $query = trim(substr($at, 6, $brace - 6));
                $rest = trim((string) preg_replace('/(?:\band\s*)?\(\s*prefers-color-scheme\s*:\s*(?:dark|light)\s*\)(?:\s*and\b)?/i', '', $query));
                $rest = trim((string) preg_replace('/^(?:only\s+)?(?:all|screen)$/i', '', $rest));
                // 1) the site toggle (html[data-theme]) wins …
                $this->ctx = '[data-theme="' . $mode . '"]';
                $toggled = $this->scope($inner, $scope);
                // 2) … the OS setting still applies when no theme attribute is set (no JS).
                $this->ctx = ':not([data-theme])';
                $os = $this->scope($inner, $scope);
                $this->ctx = '';
                $first = $rest === '' ? $toggled . "\n" : '@media ' . $rest . " {\n" . $toggled . "\n}\n";
                return $first . $header . "\n" . $os . "\n}\n";
            }
            $scopedInner = $this->scope($inner, $scope);
            return $header . "\n" . $scopedInner . "\n}\n";
        }

        // @keyframes, @font-face, @import — leave as-is (keyframes names are global; acceptable)
        return $at . (str_ends_with($at, '}') || str_ends_with($at, ';') ? "\n" : ";\n");
    }

    private function scopeRuleBlock(string $block, string $scope): string
    {
        $block = trim($block);
        if ($block === '') {
            return '';
        }

        $out = '';
        $len = strlen($block);
        $i = 0;
        while ($i < $len) {
            // find next { 
            $open = strpos($block, '{', $i);
            if ($open === false) {
                break;
            }
            $selectors = trim(substr($block, $i, $open - $i));
            $depth = 1;
            $j = $open + 1;
            while ($j < $len && $depth > 0) {
                if ($block[$j] === '{') {
                    $depth++;
                } elseif ($block[$j] === '}') {
                    $depth--;
                }
                $j++;
            }
            $body = substr($block, $open + 1, $j - $open - 2);
            if ($selectors !== '') {
                $out .= $this->prefixSelectors($selectors, $scope) . " {\n" . $body . "\n}\n";
            }
            $i = $j;
        }
        return $out;
    }

    private function prefixSelectors(string $selectors, string $scope): string
    {
        $parts = $this->splitSelectors($selectors);
        $prefixed = [];
        foreach ($parts as $sel) {
            $sel = trim($sel);
            if ($sel === '') {
                continue;
            }
            // already scoped
            if ($this->ctx === '' && str_starts_with($sel, $scope)) {
                $prefixed[] = $sel;
                continue;
            }
            // Leading html / :root / body parts: html and :root map onto the real <html> (keeping
            // conditions such as [data-theme="dark"] or .no-js); a bare body maps to the scope,
            // body with conditions (body.menu-open) stays on the real <body>.
            $htmlQ = '';
            $bodyQ = null;
            $rest = $sel;
            if (preg_match('/^(?:html|:root)((?:\[[^\]]*\]|[.#][\w-]+|:not\([^)]*\)|:(?:lang|is|where)\([^)]*\))*)(?=$|[\s>+~])\s*(?:>\s*)?(.*)$/is', $rest, $m)) {
                $htmlQ = $m[1];
                $rest = $m[2];
            }
            if (preg_match('/^body((?:\[[^\]]*\]|[.#][\w-]+|:not\([^)]*\))*)(?=$|[\s>+~])\s*(?:>\s*)?(.*)$/is', $rest, $m)) {
                $bodyQ = $m[1];
                $rest = $m[2];
            }
            $prefix = '';
            if ($htmlQ !== '' || $this->ctx !== '') {
                $prefix .= 'html' . $this->ctx . $htmlQ . ' ';
            }
            if ($bodyQ !== null && $bodyQ !== '') {
                $prefix .= 'body' . $bodyQ . ' ';
            }
            $prefixed[] = $prefix . $scope . ($rest !== '' ? ' ' . $rest : '');
        }
        return implode(",\n", array_values(array_unique($prefixed)));
    }

    /** Split a selector list on top-level commas (not inside :is(a, b) or [attr="a,b"]). */
    private function splitSelectors(string $selectors): array
    {
        $out = [];
        $buf = '';
        $depth = 0;
        $quote = '';
        $len = strlen($selectors);
        for ($i = 0; $i < $len; $i++) {
            $c = $selectors[$i];
            if ($quote !== '') {
                if ($c === $quote) {
                    $quote = '';
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($c === ',' && $depth === 0) {
                $out[] = $buf;
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        $out[] = $buf;
        return $out;
    }
}
