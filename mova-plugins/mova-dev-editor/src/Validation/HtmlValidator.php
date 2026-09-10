<?php

declare(strict_types=1);

namespace MovaDevEditor\Validation;

class HtmlValidator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** @return string[] */
    public function validate(string $html): array
    {
        $errors = [];
        if (trim($html) === '') {
            return $errors;
        }

        // Quick balance checks for common tags
        $pairs = [
            'div' => ['/<div\b/i', '/<\/div>/i'],
            'span' => ['/<span\b/i', '/<\/span>/i'],
            'p' => ['/<p\b/i', '/<\/p>/i'],
            'section' => ['/<section\b/i', '/<\/section>/i'],
            'article' => ['/<article\b/i', '/<\/article>/i'],
            'ul' => ['/<ul\b/i', '/<\/ul>/i'],
            'ol' => ['/<ol\b/i', '/<\/ol>/i'],
            'li' => ['/<li\b/i', '/<\/li>/i'],
            'table' => ['/<table\b/i', '/<\/table>/i'],
            'tr' => ['/<tr\b/i', '/<\/tr>/i'],
            'td' => ['/<td\b/i', '/<\/td>/i'],
            'th' => ['/<th\b/i', '/<\/th>/i'],
            'a' => ['/<a\b/i', '/<\/a>/i'],
            'strong' => ['/<strong\b/i', '/<\/strong>/i'],
            'em' => ['/<em\b/i', '/<\/em>/i'],
        ];

        foreach ($pairs as $tag => [$openRe, $closeRe]) {
            preg_match_all($openRe, $html, $o);
            preg_match_all($closeRe, $html, $c);
            $open = count($o[0] ?? []);
            $close = count($c[0] ?? []);
            if ($open !== $close) {
                $errors[] = "HTML: unbalanced <{$tag}> tags (open {$open}, close {$close}).";
            }
        }

        // DOMDocument parse (suppress warnings, collect issues)
        if (class_exists(\DOMDocument::class)) {
            $prev = libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $wrapped = '<?xml encoding="utf-8"><div id="mova-root">' . $html . '</div>';
            @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $libErrors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($prev);

            foreach ($libErrors as $err) {
                $msg = trim($err->message);
                // Ignore noisy charset / entity notices
                if (stripos($msg, 'htmlParseEntityRef') !== false) {
                    continue;
                }
                if (stripos($msg, 'misplaced') !== false || stripos($msg, 'Unexpected') !== false
                    || stripos($msg, 'error') !== false || stripos($msg, 'invalid') !== false) {
                    $errors[] = 'HTML: ' . $msg;
                }
            }
        }

        return $errors;
    }
}
