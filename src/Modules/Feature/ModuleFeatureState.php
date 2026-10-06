<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

/**
 * Nese stav volitelne funkce modulu
 */
final readonly class ModuleFeatureState
{
    public function __construct(
        private string $moduleCode,
        private ModuleFeatureDefinition $definition,
        private bool $installed,
        private bool $moduleEnabled,
        private bool $configuredEnabled,
        private ?string $provider,
        private bool $providerValid,
    ) {}

    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    public function code(): string
    {
        return $this->definition->code();
    }

    public function definition(): ModuleFeatureDefinition
    {
        return $this->definition;
    }

    public function installed(): bool
    {
        return $this->installed;
    }

    public function moduleEnabled(): bool
    {
        return $this->moduleEnabled;
    }

    public function configuredEnabled(): bool
    {
        return $this->configuredEnabled;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function providerValid(): bool
    {
        return $this->providerValid;
    }

    public function enabled(): bool
    {
        return $this->installed
            && $this->moduleEnabled
            && ($this->definition->required() || $this->configuredEnabled)
            && $this->providerValid;
    }
}
