<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Lifecycle\ModuleLifecycleException;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Modules\Persistence\ModuleFeatureModel;
use Lemonade\Admin\Modules\State\ModuleStateResolver;

/**
 * Ridi zivotni cyklus volitelnych funkci modulu
 */
final class ModuleFeatureLifecycleService
{
    public function __construct(
        private readonly ModuleStateResolver $modules,
        private readonly ModuleFeatureStateResolver $states,
        private readonly ModuleFeatureModel $features,
        private readonly TransactionalEventProcessor $events,
    ) {}

    /**
     * Initializes only optional toggle rows while the module install transaction is open.
     *
     * @return list<string> Feature codes inserted by this call.
     */
    public function initialize(ModuleManifestInterface $manifest, TransactionalEventCollector $events): array
    {
        if (!$manifest instanceof ModuleFeatureManifestInterface || $manifest->kind() !== ModuleKind::Optional) {
            return [];
        }

        $inserted = [];
        foreach ($this->toggleDefinitions($manifest) as $definition) {
            if (!$this->features->insertToggleIfMissing($manifest->code(), $definition->code(), $definition->defaultEnabled())) {
                continue;
            }
            $inserted[] = $definition->code();
            $events->record($this->synchronizationEvent($manifest->code(), $definition->code(), $definition->defaultEnabled()));
        }

        return $inserted;
    }

    /**
     * Adds missing toggle rows for an installed optional module without changing existing rows.
     *
     * @return list<string> Feature codes inserted by this call.
     */
    public function synchronize(ModuleManifestInterface $manifest, AuditActor $actor): array
    {
        if (!$manifest instanceof ModuleFeatureManifestInterface) {
            return [];
        }
        $module = $this->modules->state($manifest->code());
        if ($module->missingCode() || !$module->available() || !$module->installed() || $module->system()) {
            return [];
        }

        $database = $this->features->all()[$manifest->code()] ?? [];
        $pending = [];
        foreach ($this->toggleDefinitions($manifest) as $definition) {
            if (!isset($database[$definition->code()])) {
                $pending[] = $definition;
            }
        }
        if ($pending === []) {
            return [];
        }

        $inserted = $this->events->execute(
            new AuditOperation($manifest->code(), 'modules.feature.sync', $actor),
            function (TransactionalEventCollector $events) use ($manifest, $pending): array {
                $inserted = [];
                foreach ($pending as $definition) {
                    if (!$this->features->insertToggleIfMissing($manifest->code(), $definition->code(), $definition->defaultEnabled())) {
                        continue;
                    }
                    $inserted[] = $definition->code();
                    $events->record($this->synchronizationEvent($manifest->code(), $definition->code(), $definition->defaultEnabled()));
                }

                return $inserted;
            },
        );
        $this->states->refresh();

        return $inserted;
    }

    public function refresh(): void
    {
        $this->states->refresh();
    }

    public function setEnabled(string $moduleCode, string $featureCode, bool $enabled, AuditActor $actor): ModuleFeatureState
    {
        $state = $this->toggleState($moduleCode, $featureCode);
        if ($state->configuredEnabled() === $enabled) {
            return $state;
        }

        $eventCode = $enabled ? 'system.module_feature_enabled' : 'system.module_feature_disabled';
        $operationCode = $enabled ? 'modules.feature.enable' : 'modules.feature.disable';
        $this->events->execute(
            new AuditOperation($moduleCode, $operationCode, $actor),
            function (TransactionalEventCollector $events) use ($moduleCode, $featureCode, $enabled, $eventCode): void {
                $this->features->setEnabled($moduleCode, $featureCode, $enabled);
                $events->record(new DomainEvent(
                    $eventCode,
                    $moduleCode,
                    'system_module_feature',
                    $moduleCode . ':' . $featureCode,
                    [
                        'module_code' => $moduleCode,
                        'feature_code' => $featureCode,
                        'configured_enabled' => $enabled,
                    ],
                ));
            },
        );
        $this->states->refresh();

        return $this->states->feature($moduleCode, $featureCode)
            ?? throw new ModuleLifecycleException(sprintf('Feature "%s" disappeared from module "%s".', $featureCode, $moduleCode));
    }

    /** @return list<ModuleFeatureDefinition> */
    private function toggleDefinitions(ModuleFeatureManifestInterface $manifest): array
    {
        return array_values(array_filter(
            $manifest->featureDefinitions(),
            static fn(ModuleFeatureDefinition $definition): bool => $definition->category() === ModuleFeatureCategory::Toggle
                && !$definition->required(),
        ));
    }

    private function toggleState(string $moduleCode, string $featureCode): ModuleFeatureState
    {
        $module = $this->modules->state($moduleCode);
        if ($module->missingCode() || !$module->available()) {
            throw new ModuleLifecycleException(sprintf('Module "%s" has no discovered manifest.', $moduleCode));
        }
        if ($module->system() || $module->kind() !== ModuleKind::Optional || !$module->installed()) {
            throw new ModuleLifecycleException(sprintf('Optional module "%s" is not installed.', $moduleCode));
        }

        $state = $this->states->feature($moduleCode, $featureCode);
        if ($state === null || $state->definition()->category() !== ModuleFeatureCategory::Toggle || $state->definition()->required()) {
            throw new ModuleLifecycleException(sprintf('Feature "%s" is not an optional toggle of module "%s".', $featureCode, $moduleCode));
        }

        return $state;
    }

    private function synchronizationEvent(string $moduleCode, string $featureCode, bool $enabled): DomainEvent
    {
        return new DomainEvent(
            'system.module_feature_synchronized',
            $moduleCode,
            'system_module_feature',
            $moduleCode . ':' . $featureCode,
            [
                'module_code' => $moduleCode,
                'feature_code' => $featureCode,
                'configured_enabled' => $enabled,
            ],
        );
    }
}
