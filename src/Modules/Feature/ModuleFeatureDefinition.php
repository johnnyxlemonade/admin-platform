<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

use InvalidArgumentException;

/**
 * Popisuje jednu volitelnou funkci modulu
 */
final readonly class ModuleFeatureDefinition
{
    /**
     * @param list<string> $supportedProviders
     */
    public function __construct(
        private string $code,
        private ModuleFeatureCategory $category,
        private string $labelKey,
        private bool $required = false,
        private bool $defaultEnabled = false,
        private array $supportedProviders = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $this->code) !== 1) {
            throw new InvalidArgumentException(sprintf('Module feature code "%s" is invalid.', $this->code));
        }
        if (trim($this->labelKey) === '') {
            throw new InvalidArgumentException(sprintf('Module feature "%s" must have a label key.', $this->code));
        }
        if ($this->category === ModuleFeatureCategory::Toggle && $this->required) {
            throw new InvalidArgumentException(sprintf('Toggle feature "%s" cannot be required.', $this->code));
        }
        if ($this->category !== ModuleFeatureCategory::Toggle && !$this->required) {
            throw new InvalidArgumentException(sprintf('Capability feature "%s" must be required.', $this->code));
        }
        if ($this->required && !$this->defaultEnabled) {
            throw new InvalidArgumentException(sprintf('Required feature "%s" must be enabled by default.', $this->code));
        }
        $providers = [];
        foreach ($this->supportedProviders as $provider) {
            if (!is_string($provider) || preg_match('/^[a-z][a-z0-9_]*$/', $provider) !== 1) {
                throw new InvalidArgumentException(sprintf('Provider code for feature "%s" is invalid.', $this->code));
            }
            if (isset($providers[$provider])) {
                throw new InvalidArgumentException(sprintf('Provider code "%s" is duplicated for feature "%s".', $provider, $this->code));
            }
            $providers[$provider] = true;
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function category(): ModuleFeatureCategory
    {
        return $this->category;
    }

    public function labelKey(): string
    {
        return $this->labelKey;
    }

    public function required(): bool
    {
        return $this->required;
    }

    public function defaultEnabled(): bool
    {
        return $this->defaultEnabled;
    }

    /** @return list<string> */
    public function supportedProviders(): array
    {
        return $this->supportedProviders;
    }

    public function requiresProvider(): bool
    {
        return $this->supportedProviders !== [];
    }
}
