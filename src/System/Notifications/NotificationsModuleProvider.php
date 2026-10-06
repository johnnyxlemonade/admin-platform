<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Modules\Registry\ModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Admin\System\Notifications\Actions\NotificationsActionRegistrar;
use Lemonade\Admin\System\Notifications\Actions\NotificationsActivateAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsBulkActivateAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsBulkDeactivateAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsBulkDeleteAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsBulkResetDisplayAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsBulkRestoreAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsDeactivateAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsDeleteAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsResetDisplayAction;
use Lemonade\Admin\System\Notifications\Actions\NotificationsRestoreAction;
use Lemonade\Admin\System\Notifications\Audit\NotificationsAuditPresentationRegistrar;
use Lemonade\Admin\System\Notifications\DataGrid\NotificationsDataGrid;
use Lemonade\Admin\System\Notifications\Editor\NotificationsAdminEditorDefinitionFactory;
use Lemonade\Admin\System\Notifications\Editor\NotificationsEditor;
use Lemonade\Admin\System\Notifications\Http\Controller\NotificationsAudienceOptionsController;
use Lemonade\Admin\System\Notifications\Http\Controller\NotificationsExportController;
use Lemonade\Admin\System\Notifications\Migrations\RegisterNotificationsModule;
use Lemonade\Admin\System\Notifications\Routing\NotificationsRouteRegistrar;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje management contribution vedle sdilene osobni notification capability
 */
final class NotificationsModuleProvider implements ServiceProviderInterface
{
    /**
     * Zapojuje management editor, DataGrid, action a HTTP contributions pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $definition = new NotificationsModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.notifications');
        $container->get(ViewResourceRegistry::class)->register('notifications', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('notifications');
        $container->singleton(NotificationsDataGrid::class, NotificationsDataGrid::class);
        $container->singleton(NotificationsEditor::class, NotificationsEditor::class);
        $container->singleton(NotificationsAdminEditorDefinitionFactory::class, NotificationsAdminEditorDefinitionFactory::class);
        $container->scoped(NotificationsAudienceOptionsController::class, NotificationsAudienceOptionsController::class);
        $container->scoped(NotificationsExportController::class, NotificationsExportController::class);
        $container->singleton(NotificationsActivateAction::class, NotificationsActivateAction::class);
        $container->singleton(NotificationsDeactivateAction::class, NotificationsDeactivateAction::class);
        $container->singleton(NotificationsDeleteAction::class, NotificationsDeleteAction::class);
        $container->singleton(NotificationsRestoreAction::class, NotificationsRestoreAction::class);
        $container->singleton(NotificationsResetDisplayAction::class, NotificationsResetDisplayAction::class);
        $container->singleton(NotificationsBulkDeactivateAction::class, NotificationsBulkDeactivateAction::class);
        $container->singleton(NotificationsBulkDeleteAction::class, NotificationsBulkDeleteAction::class);
        $container->singleton(NotificationsBulkActivateAction::class, NotificationsBulkActivateAction::class);
        $container->singleton(NotificationsBulkResetDisplayAction::class, NotificationsBulkResetDisplayAction::class);
        $container->singleton(NotificationsBulkRestoreAction::class, NotificationsBulkRestoreAction::class);
        $container->singleton(NotificationsActionRegistrar::class, static fn(ContainerInterface $container): NotificationsActionRegistrar => new NotificationsActionRegistrar(
            $container->get(ModuleActionRegistry::class),
            $container->get(EditorDispatcher::class),
            $container->get(NotificationsActivateAction::class),
            $container->get(NotificationsDeactivateAction::class),
            $container->get(NotificationsDeleteAction::class),
            $container->get(NotificationsRestoreAction::class),
            $container->get(NotificationsResetDisplayAction::class),
            $container->get(NotificationsBulkDeactivateAction::class),
            $container->get(NotificationsBulkDeleteAction::class),
            $container->get(NotificationsBulkActivateAction::class),
            $container->get(NotificationsBulkResetDisplayAction::class),
            $container->get(NotificationsBulkRestoreAction::class),
        ));
        $container->singleton(NotificationsAuditPresentationRegistrar::class, NotificationsAuditPresentationRegistrar::class);
        $container->singleton(NotificationsModulePageProvider::class, NotificationsModulePageProvider::class);
        $container->get(ModuleRegistry::class)->register($definition);
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), $container->get(NotificationsDataGrid::class));
        $container->get(EditorRegistry::class)->register($definition->code(), $container->get(NotificationsEditor::class));
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), $container->get(NotificationsModulePageProvider::class));
        $container->get(ModulePageRegistry::class)->registerModalEditor($definition->code(), $container->get(NotificationsModulePageProvider::class));
        $container->get(NotificationsActionRegistrar::class)->register();
        $container->get(NotificationsAuditPresentationRegistrar::class)->register();
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(MigrationRegistry::class)->register(RegisterNotificationsModule::class);
        $container->singleton(NotificationsRouteRegistrar::class, NotificationsRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(NotificationsRouteRegistrar::class));
    }
}
