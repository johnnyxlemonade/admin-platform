<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

use Lemonade\Admin\Modules\Persistence\ModuleFeatureModel;
use Lemonade\Admin\Modules\State\ModuleStateResolver;

/**
 * Zjistuje stav volitelnych funkci modulu
 */
final class ModuleFeatureStateResolver
{
    /** @var array<string, array<string, array{enabled:bool,provider:string|null}>> */
    private array $databaseFeatures = [];
    private bool $databaseFeaturesLoaded = false;

    public function __construct(
        private readonly ModuleStateResolver $modules,
        private readonly ModuleFeatureModel $features,
        private readonly FeatureProviderRegistry $providers,
    ) {}

    public function feature(string $moduleCode, string $featureCode): ?ModuleFeatureState
    {
        $manifest = $this->modules->manifest($moduleCode);
        if (!$manifest instanceof ModuleFeatureManifestInterface) {
            return null;
        }

        $definition = null;
        foreach ($manifest->featureDefinitions() as $candidate) {
            if ($candidate->code() === $featureCode) {
                $definition = $candidate;
                break;
            }
        }
        if ($definition === null) {
            return null;
        }

        $moduleState = $this->modules->state($moduleCode);
        $this->databaseFeatures();
        $row = $this->databaseFeatures[$moduleCode][$featureCode] ?? null;
        $configuredEnabled = $definition->required()
            ? true
            : ($row['enabled'] ?? $definition->defaultEnabled());
        $provider = $definition->requiresProvider() ? ($row['provider'] ?? null) : null;
        $providerValid = !$definition->requiresProvider()
            || ($provider !== null
                && in_array($provider, $definition->supportedProviders(), true)
                && $this->providers->has($provider));

        return new ModuleFeatureState(
            $moduleCode,
            $definition,
            $moduleState->installed(),
            $moduleState->enabled(),
            $configuredEnabled,
            $provider,
            $providerValid,
        );
    }

    public function refresh(): void
    {
        $this->databaseFeatures = [];
        $this->databaseFeaturesLoaded = false;
    }

    /** @return array<string, array<string, array{enabled:bool,provider:string|null}>> */
    private function databaseFeatures(): array
    {
        if (!$this->databaseFeaturesLoaded) {
            $this->databaseFeatures = $this->features->all();
            $this->databaseFeaturesLoaded = true;
        }

        return $this->databaseFeatures;
    }
}
