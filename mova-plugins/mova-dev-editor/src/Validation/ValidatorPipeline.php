<?php

declare(strict_types=1);

namespace MovaDevEditor\Validation;

/**
 * Validation disabled (pass-through) — re-enable checks when ready.
 */
class ValidatorPipeline
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array{errors: string[], warnings: string[]}
     */
    public function validate(string $html, string $css, string $js): array
    {
        return ['errors' => [], 'warnings' => []];
    }
}
