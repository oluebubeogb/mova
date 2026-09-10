<?php

declare(strict_types=1);

namespace MovaDevEditor\Validation;

class JsValidator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** @return string[] */
    public function validate(string $js): array
    {
        $errors = [];
        if (trim($js) === '') {
            return $errors;
        }

        // Strip // and /* */ comments roughly for balance checks
        $stripped = preg_replace('!//.*$!m', '', $js) ?? $js;
        $stripped = preg_replace('!/\*.*?\*/!s', '', $stripped) ?? $stripped;

        $pairs = [
            ['{', '}'],
            ['(', ')'],
            ['[', ']'],
        ];
        foreach ($pairs as [$a, $b]) {
            $oa = substr_count($stripped, $a);
            $ob = substr_count($stripped, $b);
            if ($oa !== $ob) {
                $errors[] = "JavaScript: unbalanced '{$a}' / '{$b}' (open {$oa}, close {$ob}).";
            }
        }

        // Obvious incomplete statements
        if (preg_match('/\bfunction\s*\([^)]*$/m', $stripped)) {
            $errors[] = 'JavaScript: possible incomplete function declaration.';
        }

        return $errors;
    }
}
