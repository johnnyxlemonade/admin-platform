<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms;

use Lemonade\Admin\Cms\Http\Controller\PublicCmsRouteController;
use Lemonade\Admin\Cms\Routing\CmsRouteCandidateResolver;
use Lemonade\Admin\Cms\Routing\CmsRouteRepository;
use Lemonade\Admin\Cms\Routing\CmsRouteRepositoryInterface;
use Lemonade\Admin\Cms\Routing\CmsRouteReservationService;
use Lemonade\Admin\Cms\Routing\ModuleLifecyclePublicModuleStateResolver;
use Lemonade\Admin\Cms\Routing\ModuleRoutePrefixPublicRepository;
use Lemonade\Admin\Cms\Routing\PublicCmsCollectionHandlerRegistry;
use Lemonade\Admin\Cms\Routing\PublicCmsRouteHandlerRegistry;
use Lemonade\Admin\Cms\Routing\PublicCmsRouteRegistrar;
use Lemonade\Admin\Cms\Routing\PublicCmsRouteResolver;
use Lemonade\Admin\Cms\Routing\PublicCmsUrlBuilder;
use Lemonade\Admin\Cms\Routing\PublicLocaleRegistryInterface;
use Lemonade\Admin\Cms\Routing\PublicLocaleResolver;
use Lemonade\Admin\Cms\Routing\PublicModuleRoutePrefixRepositoryInterface;
use Lemonade\Admin\Cms\Routing\PublicModuleStateResolverInterface;
use Lemonade\Admin\Cms\Routing\SystemPublicLocaleRegistry;
use Lemonade\Admin\Localization\LanguageRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Registruje reusable CMS routing, locale projekce a verejny HTTP transport
 */
final class AdminCmsServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje CMS routing capability bez Portal frontend presentation
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(LanguageRegistry::class, LanguageRegistry::class);
        $container->singleton(CmsRouteRepository::class, CmsRouteRepository::class);
        $container->singleton(CmsRouteCandidateResolver::class, CmsRouteCandidateResolver::class);
        $container->singleton(CmsRouteReservationService::class, CmsRouteReservationService::class);
        $container->singleton(
            CmsRouteRepositoryInterface::class,
            static fn(ContainerInterface $container): CmsRouteRepository => $container->get(CmsRouteRepository::class),
        );
        $container->singleton(PublicLocaleRegistryInterface::class, SystemPublicLocaleRegistry::class);
        $container->singleton(PublicLocaleResolver::class, PublicLocaleResolver::class);
        $container->singleton(PublicCmsUrlBuilder::class, PublicCmsUrlBuilder::class);
        $container->singleton(PublicModuleStateResolverInterface::class, ModuleLifecyclePublicModuleStateResolver::class);
        $container->singleton(PublicModuleRoutePrefixRepositoryInterface::class, ModuleRoutePrefixPublicRepository::class);
        $container->singleton(PublicCmsRouteHandlerRegistry::class, PublicCmsRouteHandlerRegistry::class);
        $container->singleton(PublicCmsCollectionHandlerRegistry::class, PublicCmsCollectionHandlerRegistry::class);
        $container->scoped(PublicCmsRouteResolver::class, PublicCmsRouteResolver::class);
        $container->scoped(PublicCmsRouteController::class, PublicCmsRouteController::class);
        $container->singletonTagged(PublicCmsRouteRegistrar::class, PublicCmsRouteRegistrar::class, RouteRegistrarInterface::class);
    }
}
