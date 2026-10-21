<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Dashboard\DashboardWidgetRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Modules\Dashboard\ModulesDashboardWidgetProvider;
use Lemonade\Admin\System\Modules\DataGrid\ModulesDataGrid;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje Admin management contribution nad sdilenym module runtime
 */
final readonly class ModulesModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Nevyzaduje vlastni container definice pred freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void {}

    /**
     * Pripoji Modules resources a registry contributions po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $definition = new ModulesModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.modules');
        $container->get(ViewResourceRegistry::class)->register('modules', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('modules');
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), ModulesDataGrid::class);
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), ModulesModulePageProvider::class);
        $container->get(DashboardWidgetRegistry::class)->registerProvider(ModulesDashboardWidgetProvider::class);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
    }
}
