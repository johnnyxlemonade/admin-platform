<?php

declare(strict_types=1);

namespace Lemonade\Admin;

use Lemonade\Admin\Assets\AdminAssetsPublishCommand;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Http\Middleware\AdminAuthorizationRequestCacheMiddleware;
use Lemonade\Admin\Http\Middleware\AdminInstallationGuard;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Localization\DatabaseTranslationOverrideProvider;
use Lemonade\Admin\Localization\PublicLocaleSnapshotCacheInvalidator;
use Lemonade\Admin\Localization\TranslationOverrideCache;
use Lemonade\Admin\Localization\TranslationOverrideCacheInvalidator;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Routing\AdminRoutePrefixRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
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
        $container->singleton(AdminModuleRegistry::class, AdminModuleRegistry::class);
        $container->singleton(AuditEventPresentationRegistry::class, AuditEventPresentationRegistry::class);
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
            $container->get(MiddlewareStack::class)->prepend(AdminAuthorizationRequestCacheMiddleware::class);
        }

    }
}
