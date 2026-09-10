<?php

declare(strict_types=1);

namespace MovaDevEditor\Validation;

class CssValidator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** @return string[] */
    public function validate(string $css): array
    {
        $errors = [];
        if (trim($css) === '') {
            return $errors;
        }

        // Brace balance
        $open = substr_count($css, '{');
        $close = substr_count($css, '}');
        if ($open !== $close) {
            $errors[] = "CSS: unbalanced braces ({{$open}} open, {{$close}} close).";
        }

        // Parentheses balance (for calc, media, etc.)
        $po = substr_count($css, '(');
        $pc = substr_count($css, ')');
        if ($po !== $pc) {
            $errors[] = "CSS: unbalanced parentheses.";
        }

        // Strip comments then look for obviously broken rules
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css) ?? $css;

        // Unclosed strings
        if (preg_match_all('/(["\'])(?:\\\\.|[^\\\\])*?\1/', $stripped, $m) === false) {
            // ignore
        }
        // Simple odd quote count heuristic outside of matched pairs is hard; skip

        // expression() / -moz-binding (legacy XSS vectors)
        if (preg_match('/expression\s*\(/i', $stripped)) {
            $errors[] = 'CSS: expression() is not allowed.';
        }
        if (preg_match('/-moz-binding\s*:/i', $stripped)) {
            $errors[] = 'CSS: -moz-binding is not allowed.';
        }
        if (preg_match('/behavior\s*:/i', $stripped)) {
            $errors[] = 'CSS: behavior property is not allowed.';
        }

        return $errors;
    }
}
