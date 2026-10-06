<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\State;

use Lemonade\Admin\Modules\Feature\FeatureProviderRegistry;
use Lemonade\Admin\Modules\Feature\ModuleConfigurationException;
use Lemonade\Admin\Modules\Feature\ModuleFeatureState;
use Lemonade\Admin\Modules\Feature\ModuleFeatureStateResolver;
use Lemonade\Admin\Modules\Persistence\ModuleFeatureModel;

/**
 * Ridi instalaci a zivotni cyklus modulu
 */
final class ModuleManager
{
    private readonly ?ModuleFeatureStateResolver $featureStates;

    public function __construct(
        FeatureProviderRegistry $providers,
        private readonly ModuleStateResolver $states,
        ?ModuleFeatureModel $features = null,
        ?ModuleFeatureStateResolver $featureStates = null,
    ) {
        $this->featureStates = $featureStates
            ?? ($features !== null ? new ModuleFeatureStateResolver($states, $features, $providers) : null);
    }

    public function available(string $code): bool
    {
        return $this->states->state($code)->available();
    }

    public function installed(string $code): bool
    {
        return $this->states->state($code)->installed();
    }

    public function enabled(string $code): bool
    {
        return $this->states->state($code)->enabled();
    }

    public function featureEnabled(string $code, string $feature): bool
    {
        return $this->feature($code, $feature)?->enabled() ?? false;
    }

    public function featureProvider(string $code, string $feature): ?string
    {
        $state = $this->feature($code, $feature);
        if ($state === null || !$state->installed() || !$state->moduleEnabled() || !$state->configuredEnabled()) {
            return null;
        }
        if (!$state->providerValid()) {
            if (!$state->definition()->requiresProvider()) {
                return null;
            }
            throw new ModuleConfigurationException('Module feature provider is not available.');
        }
        if (!$state->enabled()) {
            return null;
        }

        return $state->provider();
    }

    public function featureSupported(string $code, string $feature): bool
    {
        return $this->feature($code, $feature) !== null;
    }

    public function feature(string $code, string $feature): ?ModuleFeatureState
    {
        if ($this->featureStates === null) {
            return null;
        }

        return $this->featureStates->feature($code, $feature);
    }
}
