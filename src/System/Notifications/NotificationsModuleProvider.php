<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications;

use Lemonade\Admin\Action\StandardEditorAction;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorRegistry;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Admin\System\Notifications\Actions\NotificationsActionRegistrar;
use Lemonade\Admin\System\Notifications\DataGrid\NotificationsDataGrid;
use Lemonade\Admin\System\Notifications\Editor\NotificationsEditor;
use Lemonade\Admin\System\Notifications\Http\Controller\NotificationsAudienceOptionsController;
use Lemonade\Admin\System\Notifications\Http\Controller\NotificationsExportController;
use Lemonade\Admin\System\Notifications\Routing\NotificationsRouteRegistrar;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje management contribution vedle sdilene osobni notification capability
 */
final readonly class NotificationsModuleProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Zapojuje management editor, DataGrid, action a HTTP contributions pred container freeze boundary
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->scoped(NotificationsAudienceOptionsController::class, NotificationsAudienceOptionsController::class);
        $container->scoped(NotificationsExportController::class, NotificationsExportController::class);
        $container->scoped(NotificationsActionRegistrar::CREATE_HANDLER, static fn(ContainerInterface $container): StandardEditorAction => StandardEditorAction::create($container->get(EditorDispatcher::class), 'system.notifications'));
        $container->scoped(NotificationsActionRegistrar::SAVE_HANDLER, static fn(ContainerInterface $container): StandardEditorAction => StandardEditorAction::save($container->get(EditorDispatcher::class), 'system.notifications'));
        $container->singleton(NotificationsActionRegistrar::class, NotificationsActionRegistrar::class);
    }

    /**
     * Pripoji Notifications resources a registry contributions po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $definition = new NotificationsModuleDefinition();
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'system.notifications');
        $container->get(ViewResourceRegistry::class)->register('notifications', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('notifications');
        $container->get(AdminModuleRegistry::class)->register($definition);
        $container->get(DataGridRegistry::class)->register($definition->code(), NotificationsDataGrid::class);
        $container->get(EditorRegistry::class)->register($definition->code(), NotificationsEditor::class);
        $container->get(ModulePageRegistry::class)->registerIndex($definition->code(), NotificationsModulePageProvider::class);
        $container->get(ModulePageRegistry::class)->registerModalEditor($definition->code(), NotificationsModulePageProvider::class);
        $container->get(NotificationsActionRegistrar::class)->register();
        $container->get(AuditEventPresentationRegistry::class)->registerModuleDefinition($definition);
        $container->get(PermissionCatalogRegistry::class)->register(...$definition->permissionDefinitions());
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(NotificationsRouteRegistrar::class));
    }
}
