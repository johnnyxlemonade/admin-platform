<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

/**
 * Registruje a vydava objekty dane casti aplikace
 */
final class FeatureProviderRegistry
{
    /** @var array<string, true> */
    private array $providers = ['local' => true];

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }
}
