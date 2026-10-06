<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\StandardEditorAction;
use Lemonade\Admin\Editor\EditorDispatcher;

/**
 * Registruje management action transport pro editor a lifecycle oznameni
 */
final class NotificationsActionRegistrar
{
    /**
     * Nastavuje registry a handlery management action
     */
    public function __construct(
        private readonly ModuleActionRegistry $actions,
        private readonly EditorDispatcher $editors,
        private readonly NotificationsActivateAction $activate,
        private readonly NotificationsDeactivateAction $deactivate,
        private readonly NotificationsDeleteAction $delete,
        private readonly NotificationsRestoreAction $restore,
        private readonly NotificationsResetDisplayAction $resetDisplay,
        private readonly NotificationsBulkDeactivateAction $bulkDeactivate,
        private readonly NotificationsBulkDeleteAction $bulkDelete,
        private readonly NotificationsBulkActivateAction $bulkActivate,
        private readonly NotificationsBulkResetDisplayAction $bulkResetDisplay,
        private readonly NotificationsBulkRestoreAction $bulkRestore,
    ) {}

    /**
     * Pripojuje editorove, jednotlive a hromadne lifecycle akce s jejich pravy
     */
    public function register(): void
    {
        $moduleCode = 'system.notifications';

        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'create',
                permission: 'system.notifications.publish',
                labelKey: 'notifications.actions.publish',
                refreshGrid: true,
            ),
            handler: StandardEditorAction::create(
                editors: $this->editors,
                module: $moduleCode,
            ),
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'save',
                permission: 'system.notifications.publish',
                labelKey: 'admin.common.save',
                refreshGrid: true,
            ),
            handler: StandardEditorAction::save(
                editors: $this->editors,
                module: $moduleCode,
            ),
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'activate',
                permission: 'system.notifications.activate',
                labelKey: 'notifications.actions.activate',
                confirmation: new ConfirmationDefinition('notifications.confirm.activate'),
                refreshGrid: true,
            ),
            handler: $this->activate,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'deactivate',
                permission: 'system.notifications.deactivate',
                labelKey: 'notifications.actions.deactivate',
                confirmation: new ConfirmationDefinition('notifications.confirm.deactivate'),
                refreshGrid: true,
            ),
            handler: $this->deactivate,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'delete',
                permission: 'system.notifications.delete',
                labelKey: 'notifications.actions.delete',
                confirmation: new ConfirmationDefinition('notifications.confirm.delete'),
                refreshGrid: true,
            ),
            handler: $this->delete,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'restore',
                permission: 'system.notifications.restore',
                labelKey: 'notifications.actions.restore',
                confirmation: new ConfirmationDefinition('notifications.confirm.restore'),
                refreshGrid: true,
            ),
            handler: $this->restore,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'reset-display',
                permission: 'system.notifications.reset_display',
                labelKey: 'notifications.actions.reset_display',
                confirmation: new ConfirmationDefinition('notifications.confirm.reset_display'),
                refreshGrid: true,
            ),
            handler: $this->resetDisplay,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'bulk-deactivate',
                permission: 'system.notifications.deactivate',
                labelKey: 'notifications.actions.deactivate',
                confirmation: new ConfirmationDefinition('notifications.confirm.deactivate'),
                refreshGrid: true,
            ),
            handler: $this->bulkDeactivate,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'bulk-delete',
                permission: 'system.notifications.delete',
                labelKey: 'notifications.actions.delete',
                confirmation: new ConfirmationDefinition('notifications.confirm.delete'),
                refreshGrid: true,
            ),
            handler: $this->bulkDelete,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'bulk-activate',
                permission: 'system.notifications.activate',
                labelKey: 'notifications.actions.activate',
                confirmation: new ConfirmationDefinition('notifications.confirm.activate'),
                refreshGrid: true,
            ),
            handler: $this->bulkActivate,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'bulk-reset-display',
                permission: 'system.notifications.reset_display',
                labelKey: 'notifications.actions.reset_display',
                confirmation: new ConfirmationDefinition('notifications.confirm.reset_display'),
                refreshGrid: true,
            ),
            handler: $this->bulkResetDisplay,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'bulk-restore',
                permission: 'system.notifications.restore',
                labelKey: 'notifications.actions.restore',
                confirmation: new ConfirmationDefinition('notifications.confirm.restore_inactive'),
                refreshGrid: true,
            ),
            handler: $this->bulkRestore,
        );
    }
}
