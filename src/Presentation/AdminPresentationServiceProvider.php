<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Editor\AdminEditor\AdminEditorFieldRenderer;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Http\Controller\AdminErrorController;
use Lemonade\Admin\Http\Controller\TranslationResourceController;
use Lemonade\Admin\Navigation\Http\Controller\NavigationTranslationController;
use Lemonade\Admin\Presentation\Http\Controller\AdminFileUploadController;
use Lemonade\Admin\Presentation\Routing\AdminFileUploadRouteRegistrar;
use Lemonade\Admin\Routing\AdminErrorRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\View;
use Lemonade\Framework\View\ViewResourceRegistry;
use Lemonade\Image\Contract\ImageAssetResolverInterface;

/**
 * Registruje sdileny rendering, odpovedi a zdroje administracni prezentace
 */
final readonly class AdminPresentationServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Urcuje sdilenou infrastrukturu potrebnou pro admin presentation
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zapisuje presentation sluzby, controllery, routy a resource roots
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(AdminFileUsageRegistry::class, AdminFileUsageRegistry::class);
        $container->transient(ImageAssetResolverInterface::class, AdminFileImageAssetResolver::class);
        $container->scoped(AdminPageRenderer::class, AdminPageRenderer::class);
        $container->scoped(AdminResponseFactory::class, AdminResponseFactory::class);
        $container->scoped(AdminErrorController::class, AdminErrorController::class);
        $container->scoped(NavigationTranslationController::class, NavigationTranslationController::class);
        $container->scoped(TranslationResourceController::class, TranslationResourceController::class);
        $container->scoped(AdminFileUploadController::class, AdminFileUploadController::class);
    }

    /**
     * Registruje presentation runtime contributions po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminRouteRegistrar::class));
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminErrorRouteRegistrar::class));
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminFileUploadRouteRegistrar::class));
        $container->get(AdminFileAuditPresentationRegistrar::class)->register();
        $container->get(TranslationResourceRegistry::class)->register(dirname(__DIR__) . '/Resources/lang', 'admin');
        $container->get(ViewResourceRegistry::class)->register('admin', dirname(__DIR__) . '/Resources/views');
        $container->get(View::class)->share('adminEditorFields', $container->get(AdminEditorFieldRenderer::class));
    }
}
