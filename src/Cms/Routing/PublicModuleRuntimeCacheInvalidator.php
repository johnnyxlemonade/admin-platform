<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Routing;

use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventListenerInterface;
use Lemonade\Admin\Modules\State\ModuleStateResolver;

/**
 * Invaliduje persistentni public metadata modulu az po uspesnem commitu
 */
final readonly class PublicModuleRuntimeCacheInvalidator implements DomainEventListenerInterface
{
    public function __construct(
        private ModuleStateResolver $modules,
        private ModuleRoutePrefixPublicRepository $prefixes,
    ) {}

    /**
     * Reaguje jen na udalosti menici public module runtime metadata
     */
    public function handle(DomainEvent $event): void
    {
        if (in_array($event->code(), [
            'system.module_installed',
            'system.module_enabled',
            'system.module_disabled',
        ], true)) {
            $this->modules->forgetCachedDatabaseStates();
        }
        if ($event->code() === 'system.module_route_prefix_synchronized') {
            $this->prefixes->forgetCachedPrefixes();
        }
    }
}
