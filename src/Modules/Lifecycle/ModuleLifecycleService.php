<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Lifecycle;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Authorization\PermissionSyncService;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Modules\Feature\ModuleFeatureLifecycleService;
use Lemonade\Admin\Modules\Migration\ModuleMigrationManifestInterface;
use Lemonade\Admin\Modules\Migration\ModuleMigrationRunner;
use Lemonade\Admin\Modules\Persistence\ModuleModel;
use Lemonade\Admin\Modules\Routing\ModuleRoutePrefixLifecycleService;
use Lemonade\Admin\Modules\State\ModuleState;
use Lemonade\Admin\Modules\State\ModuleStateResolver;

/**
 * Ridi zivotni cyklus modulu
 */
final class ModuleLifecycleService
{
    public function __construct(
        private readonly ModuleStateResolver $states,
        private readonly ModuleModel $modules,
        private readonly TransactionalEventProcessor $events,
        private readonly ?ModuleMigrationRunner $migrations = null,
        private readonly ?ModuleFeatureLifecycleService $features = null,
        private readonly ?ModuleRoutePrefixLifecycleService $routePrefixes = null,
        private readonly ?PermissionSyncService $permissions = null,
    ) {}

    public function enable(string $code, AuditActor $actor): ModuleState
    {
        return $this->change($code, true, $actor);
    }

    public function disable(string $code, AuditActor $actor): ModuleState
    {
        return $this->change($code, false, $actor);
    }

    public function install(string $code, AuditActor $actor): ModuleState
    {
        $state = $this->states->state($code);
        if ($state->missingCode()) {
            throw new ModuleLifecycleException(sprintf('Module "%s" has no discovered manifest.', $code));
        }
        if (!$state->available()) {
            throw new ModuleLifecycleException(sprintf('Module "%s" is not discovered.', $code));
        }
        if ($state->system()) {
            throw new ModuleLifecycleException(sprintf('System module "%s" cannot be installed through lifecycle.', $code));
        }
        if ($state->installed()) {
            return $state;
        }
        $manifest = $this->states->manifest($code);
        if (!$manifest instanceof ModuleMigrationManifestInterface) {
            throw new ModuleLifecycleException(sprintf('Optional module "%s" does not declare migrations.', $code));
        }

        if ($this->migrations === null) {
            throw new ModuleLifecycleException('Module migration runner is not available.');
        }
        $this->migrations->migrate($manifest);
        $this->events->execute(new AuditOperation($code, 'modules.install', $actor), function (TransactionalEventCollector $events) use ($code, $manifest): void {
            $this->permissions?->syncModule($code);
            $this->modules->install($code);
            $this->routePrefixes?->initialize($manifest, $events);
            $this->features?->initialize($manifest, $events);
            $events->record(new DomainEvent('system.module_installed', $code, 'system.module', $code));
        });
        $this->states->refresh();
        $this->features?->refresh();

        return $this->states->state($code);
    }

    /** @return array<string, list<string>> */
    public function migrateInstalled(?AuditActor $actor = null): array
    {
        $actor ??= AuditActor::migration('modules-migrate');
        $results = [];
        foreach ($this->states->all() as $state) {
            if (!$state->installed() || $state->system() || $state->missingCode()) {
                continue;
            }
            $manifest = $this->states->manifest($state->code());
            if (!$manifest instanceof ModuleMigrationManifestInterface) {
                continue;
            }
            if ($this->migrations === null) {
                throw new ModuleLifecycleException('Module migration runner is not available.');
            }
            $results[$state->code()] = $this->migrations->migrate($manifest)->applied();
            $this->routePrefixes?->synchronize($manifest, $actor);
            $this->features?->synchronize($manifest, $actor);
        }

        return $results;
    }

    private function change(string $code, bool $enabled, AuditActor $actor): ModuleState
    {
        $state = $this->states->state($code);
        if ($state->missingCode()) {
            throw new ModuleLifecycleException(sprintf('Module "%s" has no discovered manifest.', $code));
        }
        if (!$state->available() || !$state->installed()) {
            throw new ModuleLifecycleException(sprintf('Module "%s" is not installed.', $code));
        }
        if ($state->system()) {
            throw new ModuleLifecycleException(sprintf('System module "%s" cannot be disabled or enabled through lifecycle.', $code));
        }
        if ($state->enabled() === $enabled) {
            return $state;
        }

        $operation = new AuditOperation($code, $enabled ? 'modules.enable' : 'modules.disable', $actor);
        $eventCode = $enabled ? 'system.module_enabled' : 'system.module_disabled';
        $this->events->execute($operation, function (TransactionalEventCollector $events) use ($code, $enabled, $eventCode): void {
            $this->modules->setEnabled($code, $enabled);
            $events->record(new DomainEvent($eventCode, $code, 'system_module', $code, ['enabled' => $enabled]));
        });
        $this->states->refresh();

        return $this->states->state($code);
    }
}
