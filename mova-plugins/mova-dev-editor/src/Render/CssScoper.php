<?php

declare(strict_types=1);

namespace MovaDevEditor\Render;

/**
 * Prefix CSS selectors so Dev Mode styles cannot affect site chrome or HQ.
 */
class CssScoper
{
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
        $parts = array_map('trim', explode(',', $selectors));
        $prefixed = [];
        foreach ($parts as $sel) {
            if ($sel === '') {
                continue;
            }
            // body/html → scope only (do not restyle real body)
            if (preg_match('/^(html|body|:root)\b/i', $sel)) {
                $prefixed[] = $scope;
                continue;
            }
            // already scoped
            if (str_starts_with($sel, $scope)) {
                $prefixed[] = $sel;
                continue;
            }
            $prefixed[] = $scope . ' ' . $sel;
        }
        return implode(",\n", $prefixed);
    }
}
