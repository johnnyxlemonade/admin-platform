<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Admin\System\Media\DataGrid\MediaDataGrid;
use Lemonade\Admin\System\Media\Http\Controller\MediaDownloadController;
use Lemonade\Admin\System\Media\Migrations\RegisterMediaModule;
use Lemonade\Admin\System\Media\Routing\MediaRouteRegistrar;
use Lemonade\Admin\System\Media\Services\MediaService;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;

/**
 * Registruje tenkou Admin presentation vrstvu nad shared system_file contractem
 */
final class MediaModuleProvider implements ServiceProviderInterface
{
    /**
     * Pripojuje read-only definici, grid, preklady a registracni migraci
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definition = new MediaModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.media');
        $container->get(ClientTranslationGroupRegistry::class)->register('media');
        $container->singleton(MediaService::class, MediaService::class);
        $container->singleton(MediaDataGrid::class, MediaDataGrid::class);
        $container->scoped(MediaDownloadController::class, MediaDownloadController::class);
        $container->singleton(MediaRouteRegistrar::class, MediaRouteRegistrar::class);
        $container->singleton(MediaModulePageProvider::class, MediaModulePageProvider::class);
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(MediaDataGrid::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(MediaModulePageProvider::class));
        $container->get(MigrationRegistry::class)->register(RegisterMediaModule::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(MediaRouteRegistrar::class));
    }
}
