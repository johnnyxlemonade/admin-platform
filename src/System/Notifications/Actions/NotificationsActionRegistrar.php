<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

use Lemonade\Admin\Action\ModuleActionDefinition;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;

/**
 * Registruje management action transport pro editor a lifecycle oznameni
 */
final readonly class NotificationsActionRegistrar
{
    public const string CREATE_HANDLER = 'admin.system.notifications.action.create';
    public const string SAVE_HANDLER = 'admin.system.notifications.action.save';

    /**
     * Nastavuje registry a handlery management action
     */
    public function __construct(
        private ModuleActionRegistry $actions,
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
            handlerServiceId: self::CREATE_HANDLER,
        );
        $this->actions->register(
            moduleCode: $moduleCode,
            definition: new ModuleActionDefinition(
                key: 'save',
                permission: 'system.notifications.publish',
                labelKey: 'admin.common.save',
                refreshGrid: true,
            ),
            handlerServiceId: self::SAVE_HANDLER,
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
            handlerServiceId: NotificationsActivateAction::class,
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
            handlerServiceId: NotificationsDeactivateAction::class,
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
            handlerServiceId: NotificationsDeleteAction::class,
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
            handlerServiceId: NotificationsRestoreAction::class,
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
            handlerServiceId: NotificationsResetDisplayAction::class,
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
            handlerServiceId: NotificationsBulkDeactivateAction::class,
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
            handlerServiceId: NotificationsBulkDeleteAction::class,
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
            handlerServiceId: NotificationsBulkActivateAction::class,
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
            handlerServiceId: NotificationsBulkResetDisplayAction::class,
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
            handlerServiceId: NotificationsBulkRestoreAction::class,
        );
    }
}
