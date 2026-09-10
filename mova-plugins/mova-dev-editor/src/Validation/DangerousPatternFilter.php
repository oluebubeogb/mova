<?php

declare(strict_types=1);

namespace MovaDevEditor\Validation;

class DangerousPatternFilter
{
    private array $patterns;

    public function __construct(array $config)
    {
        $this->patterns = $config['dangerous_patterns'] ?? [];
    }

    /**
     * @return string[] human-readable hits
     */
    public function scan(string $code): array
    {
        $hits = [];
        foreach ($this->patterns as $pattern) {
            if (@preg_match($pattern, $code)) {
                if (preg_match($pattern, $code)) {
                    $hits[] = $this->label($pattern);
                }
            }
        }
        return $hits;
    }

    private function label(string $pattern): string
    {
        $map = [
            'eval' => 'eval()',
            'Function' => 'Function() constructor',
            'document.cookie' => 'document.cookie access',
            'localStorage' => 'localStorage access',
            'sessionStorage' => 'sessionStorage access',
            'javascript:' => 'javascript: URL',
            'on\\w+' => 'inline event handler',
            'https?:' => 'external script src',
            'Worker' => 'Web Worker',
            'XMLHttpRequest' => 'XMLHttpRequest',
            'fetch' => 'external fetch()',
        ];
        foreach ($map as $needle => $label) {
            if (stripos($pattern, $needle) !== false) {
                return $label;
            }
        }
        return $pattern;
    }
}
