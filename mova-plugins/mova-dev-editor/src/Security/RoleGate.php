<?php

declare(strict_types=1);

namespace MovaDevEditor\Security;

use Mova\Auth\Auth;

class RoleGate
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function canUseDevMode(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        $allowed = $this->config['allowed_roles'] ?? ['owner', 'administrator'];
        return Auth::hasRole(...$allowed);
    }
}
