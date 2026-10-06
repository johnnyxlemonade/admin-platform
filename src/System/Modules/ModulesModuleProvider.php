<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Admin\System\Modules\Actions\ModulesActionRegistrar;
use Lemonade\Admin\System\Modules\Actions\ModulesDisableAction;
use Lemonade\Admin\System\Modules\Actions\ModulesEnableAction;
use Lemonade\Admin\System\Modules\Actions\ModulesFeatureToggleAction;
use Lemonade\Admin\System\Modules\Actions\ModulesInstallAction;
use Lemonade\Admin\System\Modules\Dashboard\ModulesDashboardWidgetProvider;
use Lemonade\Admin\System\Modules\DataGrid\ModulesDataGrid;
use Lemonade\Admin\System\Modules\Http\Controller\ModulesFeaturesController;
use Lemonade\Admin\System\Modules\Migrations\RegisterModulesModule;
use Lemonade\Admin\System\Modules\Routing\ModulesRouteRegistrar;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje Admin management contribution nad sdilenym module runtime
 */
final class ModulesModuleProvider implements ServiceProviderInterface
{
    /**
     * Zapojuje DataGrid, action transport, presentation a route contributions pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definition = new ModulesModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.modules');
        $container->get(ViewResourceRegistry::class)->register('modules', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('modules');
        $container->singleton(ModulesDataGrid::class, ModulesDataGrid::class);
        $container->singleton(ModulesModulePageProvider::class, ModulesModulePageProvider::class);
        $container->singletonTagged(ModulesDashboardWidgetProvider::class, ModulesDashboardWidgetProvider::class, DashboardWidgetProviderInterface::class);
        $container->singleton(ModulesInstallAction::class, ModulesInstallAction::class);
        $container->singleton(ModulesEnableAction::class, ModulesEnableAction::class);
        $container->singleton(ModulesDisableAction::class, ModulesDisableAction::class);
        $container->singleton(ModulesFeatureToggleAction::class, ModulesFeatureToggleAction::class);
        $container->singleton(ModulesActionRegistrar::class, ModulesActionRegistrar::class);
        $container->singleton(ModulesFeaturesPageProvider::class, ModulesFeaturesPageProvider::class);
        $container->scoped(ModulesFeaturesController::class, ModulesFeaturesController::class);
        $container->singleton(ModulesRouteRegistrar::class, ModulesRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(ModulesRouteRegistrar::class));
        $container->get(ModuleRegistry::class)->register($definition);
        $audit = $container->get(AuditEventPresentationRegistry::class);
        $audit->registerModule(new AuditModulePresentation('system.modules', 'modules', 'modules.module.name', AdminIcon::Boxes));
        foreach (['installed', 'enabled', 'disabled', 'route_prefix_synchronized', 'feature_enabled', 'feature_disabled', 'feature_synchronized'] as $event) {
            $audit->register(new AuditEventPresentation('system.module_' . $event, 'modules.audit.' . $event, AdminIcon::Boxes));
        }
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(ModulesDataGrid::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(ModulesModulePageProvider::class));
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(ModulesActionRegistrar::class)->register();
        $container->get(MigrationRegistry::class)->register(RegisterModulesModule::class);
    }
}
