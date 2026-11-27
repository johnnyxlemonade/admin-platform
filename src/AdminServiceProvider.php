<?php

declare(strict_types=1);

namespace Lemonade\Admin;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Assets\AdminAssetsPublishCommand;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Http\Middleware\AdminAuthorizationRequestCacheMiddleware;
use Lemonade\Admin\Http\Middleware\AdminInstallationGuard;
use Lemonade\Admin\Http\Middleware\AdminModuleRequestMiddleware;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Localization\DatabaseTranslationOverrideProvider;
use Lemonade\Admin\Localization\PublicLocaleSnapshotCacheInvalidator;
use Lemonade\Admin\Localization\TranslationOverrideCache;
use Lemonade\Admin\Localization\TranslationOverrideCacheInvalidator;
use Lemonade\Admin\Module\AdminModuleContributionLoader;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Presentation\AdminFileUsageRegistry;
use Lemonade\Admin\Routing\AdminRoutePrefixRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Localization\TranslationOverrideProviderInterface;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Registruje sdilenou infrastrukturu, kterou potrebuji vsechny admin capability
 */
final readonly class AdminServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zapisuje cross-module registry, lokalizaci a vstupni middleware
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(AdminRouteRegistrarRegistry::class, AdminRouteRegistrarRegistry::class);
        $container->singletonTagged(AdminRoutePrefixRegistrar::class, AdminRoutePrefixRegistrar::class, RouteRegistrarInterface::class);

        $container->singleton(ClientTranslationGroupRegistry::class, ClientTranslationGroupRegistry::class);
        $container->singleton(TranslationOverrideCache::class, TranslationOverrideCache::class);
        $container->transient(TranslationOverrideProviderInterface::class, DatabaseTranslationOverrideProvider::class);
        $container->singleton(ClientTranslationVersion::class, ClientTranslationVersion::class);
        $container->scoped(AdminUiLocale::class, AdminUiLocale::class);
        $container->singleton(AdminNavigationGroupRegistry::class, AdminNavigationGroupRegistry::class);
        foreach ([
            AdminModuleContributionLoader::class,
            AdminModuleRegistry::class,
            AuditEventPresentationRegistry::class,
            PermissionCatalogRegistry::class,
            ModuleActionRegistry::class,
            DataGridRegistry::class,
            EditorRegistry::class,
            ModulePageRegistry::class,
            AdminFileUsageRegistry::class,
        ] as $registry) {
            $this->registerModuleRegistry($container, $registry);
        }
    }

    /**
     * Registruje runtime contributions sdilene admin infrastruktury po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        if (method_exists($container, 'isBound') && $container->isBound(CommandRegistry::class)) {
            $container->get(CommandRegistry::class)->register(AdminAssetsPublishCommand::class);
        }

        $container->get(DomainEventDispatcher::class)->listen($container->get(TranslationOverrideCacheInvalidator::class));
        $container->get(DomainEventDispatcher::class)->listen($container->get(PublicLocaleSnapshotCacheInvalidator::class));
        if (method_exists($container, 'isBound') && $container->isBound(MiddlewareStack::class)) {
            $container->get(MiddlewareStack::class)->prepend(AdminInstallationGuard::class);
            $container->get(MiddlewareStack::class)->prepend(AdminModuleRequestMiddleware::class);
            $container->get(MiddlewareStack::class)->prepend(AdminAuthorizationRequestCacheMiddleware::class);
        }

    }

    /**
     * Keeps bootstrap definitions at the root and isolates deferred writes in each runtime scope
     *
     * @param class-string $registry
     */
    private function registerModuleRegistry(ContainerBuilderInterface $builder, string $registry): void
    {
        $bootstrap = $registry . '.bootstrap';
        $runtime = $registry . '.runtime';
        $builder->singleton($bootstrap, $registry);
        $builder->scoped($runtime, static function (ContainerInterface $scope) use ($bootstrap): object {
            $prototype = $scope->get($bootstrap);
            if ($prototype instanceof AdminModuleContributionLoader) {
                return $prototype->forScope($scope);
            }
            if ($prototype instanceof AdminModuleRegistry) {
                return $prototype->withContributions($scope->get(AdminModuleContributionLoader::class));
            }

            return clone $prototype;
        });
        $builder->transient($registry, static fn(ContainerInterface $container): object => $container->get(
            $container instanceof ScopedContainerInterface ? $runtime : $bootstrap,
        ));
    }
}
