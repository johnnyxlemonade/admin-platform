<?php

declare(strict_types=1);

namespace Lemonade\Admin;

use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Assets\AdminAssetPublisher;
use Lemonade\Admin\Assets\AdminAssetsPublishCommand;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\EffectivePermissionGroupViewModelFactory;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Export\CsvExport;
use Lemonade\Admin\Http\Middleware\AdminAuthorizationRequestCacheMiddleware;
use Lemonade\Admin\Http\Middleware\AdminInstallationGuard;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationCatalog;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Localization\DatabaseTranslationOverrideProvider;
use Lemonade\Admin\Localization\TranslationOverrideCache;
use Lemonade\Admin\Localization\TranslationOverrideCacheInvalidator;
use Lemonade\Admin\Localization\TranslationOverrideModel;
use Lemonade\Admin\Localization\TranslationOverrideService;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminModuleRouteResolver;
use Lemonade\Admin\Navigation\AdminNavigation;
use Lemonade\Admin\Navigation\AdminNavigationGroupRegistry;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Admin\Routing\AdminRoutePrefixRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationDirectoryRegistrar;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Localization\TranslationOverrideProviderInterface;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Registruje sdilenou infrastrukturu, kterou potrebuji vsechny admin capability
 */
final class AdminServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje cross-module registry, lokalizaci, vstupni middleware a migrace
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(AdminAssetManifest::class, AdminAssetManifest::class);
        $container->singleton(AdminAssetPublisher::class, AdminAssetPublisher::class);
        $container->singleton(AdminAssetsPublishCommand::class, AdminAssetsPublishCommand::class);
        if ($container->isBound(CommandRegistry::class)) {
            $container->get(CommandRegistry::class)->register(AdminAssetsPublishCommand::class);
        }

        $container->singleton(AdminRouteRegistrarRegistry::class, AdminRouteRegistrarRegistry::class);
        $container->singletonTagged(AdminRoutePrefixRegistrar::class, AdminRoutePrefixRegistrar::class, RouteRegistrarInterface::class);
        if (!$container->isBound(AdminBranding::class)) {
            $container->singleton(AdminBranding::class, AdminBranding::class);
        }

        $container->singleton(ClientTranslationGroupRegistry::class, ClientTranslationGroupRegistry::class);
        $container->singleton(TranslationOverrideModel::class, TranslationOverrideModel::class);
        $container->singleton(TranslationOverrideCache::class, TranslationOverrideCache::class);
        $container->singleton(DatabaseTranslationOverrideProvider::class, DatabaseTranslationOverrideProvider::class);
        $container->singleton(TranslationOverrideProviderInterface::class, static fn(ContainerInterface $container): DatabaseTranslationOverrideProvider => $container->get(DatabaseTranslationOverrideProvider::class));
        $container->singleton(TranslationOverrideService::class, TranslationOverrideService::class);
        $container->singleton(ClientTranslationCatalog::class, ClientTranslationCatalog::class);
        $container->singleton(ClientTranslationVersion::class, ClientTranslationVersion::class);
        $container->singleton(TranslationOverrideCacheInvalidator::class, TranslationOverrideCacheInvalidator::class);
        $container->get(DomainEventDispatcher::class)->listen($container->get(TranslationOverrideCacheInvalidator::class));
        $container->singleton(AdminUiLocale::class, AdminUiLocale::class);
        $container->singleton(AdminNavigationGroupRegistry::class, AdminNavigationGroupRegistry::class);
        $container->singleton(AdminModuleRegistry::class, AdminModuleRegistry::class);
        $container->singleton(AdminModuleAccessPolicy::class, AdminModuleAccessPolicy::class);
        $container->singleton(AdminModuleRouteResolver::class, AdminModuleRouteResolver::class);
        $container->singleton(AuditEventPresentationRegistry::class, AuditEventPresentationRegistry::class);
        $container->singleton(CsvExport::class, CsvExport::class);
        $container->singleton(EffectivePermissionGroupViewModelFactory::class, EffectivePermissionGroupViewModelFactory::class);
        $container->singleton(AdminNavigation::class, AdminNavigation::class);
        $container->singleton(AdminAuthorizationRequestCacheMiddleware::class, AdminAuthorizationRequestCacheMiddleware::class);
        $container->singleton(AdminInstallationGuard::class, AdminInstallationGuard::class);

        if ($container->isBound(MiddlewareStack::class)) {
            $container->get(MiddlewareStack::class)->prepend(AdminInstallationGuard::class);
            $container->get(MiddlewareStack::class)->prepend(AdminAuthorizationRequestCacheMiddleware::class);
        }

        (new MigrationDirectoryRegistrar())->registerDirectory(
            $container->get(MigrationRegistry::class),
            __DIR__ . '/Migrations',
            'Lemonade\\Admin\\Migrations',
        );
    }
}
