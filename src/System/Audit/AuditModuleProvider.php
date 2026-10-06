<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\System\Audit\Dashboard\AuditDashboardWidgetProvider;
use Lemonade\Admin\System\Audit\Dashboard\MyLoginsDashboardWidgetProvider;
use Lemonade\Admin\System\Audit\DataGrid\AuditDataGrid;
use Lemonade\Admin\System\Audit\Migrations\RegisterAuditModule;
use Lemonade\Admin\System\Audit\Models\AuditLogModel;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje read-only auditni modul, jeho DataGrid a dashboardove widgety
 */
final class AuditModuleProvider implements ServiceProviderInterface
{
    /**
     * Registruje sluzby, grid a widgety modulu auditu
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definition = new AuditModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.audit');
        $container->get(ViewResourceRegistry::class)->register('audit', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('audit');
        $container->singleton(AuditLogModel::class, AuditLogModel::class);
        $container->singleton(PersonalLoginAuditReaderInterface::class, static fn(ContainerInterface $container): AuditLogModel => $container->get(AuditLogModel::class));
        $container->singleton(AuditEventPresenter::class, AuditEventPresenter::class);
        $container->singleton(AuditDataGrid::class, AuditDataGrid::class);
        $container->singletonTagged(AuditDashboardWidgetProvider::class, AuditDashboardWidgetProvider::class, DashboardWidgetProviderInterface::class);
        $container->singletonTagged(MyLoginsDashboardWidgetProvider::class, MyLoginsDashboardWidgetProvider::class, DashboardWidgetProviderInterface::class);
        $container->singleton(AuditModulePageProvider::class, AuditModulePageProvider::class);
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(AuditDataGrid::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(AuditModulePageProvider::class));
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(MigrationRegistry::class)->register(RegisterAuditModule::class);
    }
}
