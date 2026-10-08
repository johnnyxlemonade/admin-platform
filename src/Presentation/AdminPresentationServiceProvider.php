<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Http\AdminAuthorizationResponseHandler;
use Lemonade\Admin\Http\AdminResponseFactory;
use Lemonade\Admin\Http\Controller\AdminErrorController;
use Lemonade\Admin\Http\Controller\TranslationResourceController;
use Lemonade\Admin\Navigation\Http\Controller\NavigationTranslationController;
use Lemonade\Admin\Presentation\Http\Controller\AdminFileUploadController;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Admin\Presentation\Routing\AdminFileUploadRouteRegistrar;
use Lemonade\Admin\Routing\AdminErrorRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;
use Lemonade\Image\Contract\ImageAssetResolverInterface;
use Lemonade\Image\ImageVariantRegistry;

/**
 * Registruje sdileny rendering, odpovedi a zdroje administracni prezentace
 */
final class AdminPresentationServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
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
        $container->singleton(AdminAuthorizationResponseHandler::class, AdminAuthorizationResponseHandler::class);
        $container->singleton(AdminThumbnailComponent::class, AdminThumbnailComponent::class);
        $container->singleton(AdminFileModel::class, AdminFileModel::class);
        $container->singleton(AdminFileUsageRegistry::class, AdminFileUsageRegistry::class);
        $container->singleton(AdminFileMutationService::class, AdminFileMutationService::class);
        $container->singleton(AdminFileRenameModalDefinitionFactory::class, AdminFileRenameModalDefinitionFactory::class);
        $container->singleton(AdminFileAuditPresentationRegistrar::class, AdminFileAuditPresentationRegistrar::class);
        $container->singleton(AdminFileImageAssetResolver::class, AdminFileImageAssetResolver::class);
        $container->singleton(AdminFileOriginalPathResolver::class, AdminFileOriginalPathResolver::class);
        $container->singleton(AdminFileImageOriginalWriter::class, AdminFileImageOriginalWriter::class);
        $container->singleton(ImageAssetResolverInterface::class, AdminFileImageAssetResolver::class);
        $container->singleton(AdminFileUploadComponent::class, AdminFileUploadComponent::class);
        $container->scoped(AdminPageRenderer::class, AdminPageRenderer::class);
        $container->scoped(AdminResponseFactory::class, AdminResponseFactory::class);
        $container->scoped(AdminErrorController::class, AdminErrorController::class);
        $container->scoped(NavigationTranslationController::class, NavigationTranslationController::class);
        $container->scoped(TranslationResourceController::class, TranslationResourceController::class);
        $container->scoped(AdminFileUploadController::class, AdminFileUploadController::class);
        $container->singleton(AdminRouteRegistrar::class, AdminRouteRegistrar::class);
        $container->singleton(AdminErrorRouteRegistrar::class, AdminErrorRouteRegistrar::class);
        $container->singleton(AdminFileUploadRouteRegistrar::class, AdminFileUploadRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminRouteRegistrar::class));
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminErrorRouteRegistrar::class));
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminFileUploadRouteRegistrar::class));
        $container->get(AdminFileAuditPresentationRegistrar::class)->register();
        $container->get(ImageVariantRegistry::class)->register('datagrid', new ImageVariantDefinition(new ImageDimensions(32, 32), ImageFormat::Webp, ImageQuality::fromInt(82)));
        $container->get(ImageVariantRegistry::class)->register('thumbnail', new ImageVariantDefinition(new ImageDimensions(160, 160), ImageFormat::Webp, ImageQuality::fromInt(82)));
        $container->get(ImageVariantRegistry::class)->register('preview', new ImageVariantDefinition(new ImageDimensions(960, 540), ImageFormat::Webp, ImageQuality::fromInt(85)));

        $container->get(TranslationResourceRegistry::class)->register(dirname(__DIR__) . '/Resources/lang', 'admin');
        $container->get(ViewResourceRegistry::class)->register('admin', dirname(__DIR__) . '/Resources/views');
    }
}
