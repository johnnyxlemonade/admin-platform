<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Dashboard\DashboardWidgetRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Modules\Actions\ModulesActionRegistrar;
use Lemonade\Admin\System\Modules\Dashboard\ModulesDashboardWidgetProvider;
use Lemonade\Admin\System\Modules\DataGrid\ModulesDataGrid;
use Lemonade\Admin\System\Modules\Migrations\RegisterModulesModule;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje Admin management contribution nad sdilenym module runtime
 */
final readonly class ModulesModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zapojuje DataGrid, action transport, presentation a route contributions pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ModulesActionRegistrar::class, ModulesActionRegistrar::class);
    }

    /**
     * Pripoji Modules resources a registry contributions po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $definition = new ModulesModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.modules');
        $container->get(ViewResourceRegistry::class)->register('modules', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('modules');
        $container->get(ModuleRegistry::class)->register($definition);
        $audit = $container->get(AuditEventPresentationRegistry::class);
        $audit->registerModule(new AuditModulePresentation('system.modules', 'modules.module.name', AdminIcon::Boxes));
        foreach (['installed', 'enabled', 'disabled'] as $event) {
            $audit->register(new AuditEventPresentation('system.module_' . $event, 'modules.audit.' . $event, AdminIcon::Boxes));
        }
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), ModulesDataGrid::class);
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), ModulesModulePageProvider::class);
        $container->get(DashboardWidgetRegistry::class)->registerProvider(ModulesDashboardWidgetProvider::class);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(ModulesActionRegistrar::class)->register();
        $container->get(MigrationRegistry::class)->register(RegisterModulesModule::class);
    }
}
