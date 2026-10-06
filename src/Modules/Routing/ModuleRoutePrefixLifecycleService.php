<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Routing;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Modules\Definition\ModuleKind;
use Lemonade\Admin\Modules\Manifest\ModuleManifestInterface;
use Lemonade\Admin\Modules\Persistence\ModuleRoutePrefixModel;
use Lemonade\Admin\Modules\State\ModuleStateResolver;

/**
 * Ridi zivotni cyklus prefixu routy modulu
 */
final class ModuleRoutePrefixLifecycleService
{
    public function __construct(
        private readonly ModuleStateResolver $modules,
        private readonly ModuleRoutePrefixModel $prefixes,
        private readonly TransactionalEventProcessor $events,
    ) {}

    /** @return list<string> */
    public function initialize(ModuleManifestInterface $manifest, TransactionalEventCollector $events): array
    {
        if (!$manifest instanceof ModulePublicRoutePrefixManifestInterface || $manifest->kind() !== ModuleKind::Optional) {
            return [];
        }

        return $this->insertMissing($manifest, $events);
    }

    /** @return list<string> */
    public function synchronize(ModuleManifestInterface $manifest, AuditActor $actor): array
    {
        if (!$manifest instanceof ModulePublicRoutePrefixManifestInterface) {
            return [];
        }
        $module = $this->modules->state($manifest->code());
        if ($module->missingCode() || !$module->available() || !$module->installed() || $module->system()) {
            return [];
        }

        $existing = $this->prefixes->all()[$manifest->code()] ?? [];
        $pending = array_values(array_filter(
            $manifest->publicRoutePrefixDefinitions(),
            fn(ModulePublicRoutePrefixDefinition $definition): bool => !isset($existing[$definition->locale()])
                && $this->prefixes->localeExists($definition->locale()),
        ));
        if ($pending === []) {
            return [];
        }

        return $this->events->execute(
            new AuditOperation($manifest->code(), 'modules.route_prefix.sync', $actor),
            fn(TransactionalEventCollector $events): array => $this->insertMissing($manifest, $events, $pending),
        );
    }

    /**
     * @param list<ModulePublicRoutePrefixDefinition>|null $definitions
     * @return list<string>
     */
    private function insertMissing(ModulePublicRoutePrefixManifestInterface $manifest, TransactionalEventCollector $events, ?array $definitions = null): array
    {
        $inserted = [];
        foreach ($definitions ?? $manifest->publicRoutePrefixDefinitions() as $definition) {
            if (!$this->prefixes->insertIfMissing($manifest->code(), $definition->locale(), $definition->prefix())) {
                continue;
            }
            $inserted[] = $definition->locale();
            $events->record(new DomainEvent(
                'system.module_route_prefix_synchronized',
                $manifest->code(),
                'system_module_route_prefix',
                $manifest->code() . ':' . $definition->locale(),
                [
                    'module_code' => $manifest->code(),
                    'locale' => $definition->locale(),
                    'prefix' => $definition->prefix(),
                ],
            ));
        }

        return $inserted;
    }
}
