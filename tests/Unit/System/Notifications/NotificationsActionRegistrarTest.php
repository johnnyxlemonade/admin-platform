<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\Editor\EditorDispatcher;
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
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class NotificationsActionRegistrarTest extends TestCase
{
    public function testRegistersTheNotificationActionContract(): void
    {
        $actions = new ModuleActionRegistry();

        (new NotificationsActionRegistrar(
            actions: $actions,
            editors: $this->withoutConstructor(EditorDispatcher::class),
            activate: $this->withoutConstructor(NotificationsActivateAction::class),
            deactivate: $this->withoutConstructor(NotificationsDeactivateAction::class),
            delete: $this->withoutConstructor(NotificationsDeleteAction::class),
            restore: $this->withoutConstructor(NotificationsRestoreAction::class),
            resetDisplay: $this->withoutConstructor(NotificationsResetDisplayAction::class),
            bulkDeactivate: $this->withoutConstructor(NotificationsBulkDeactivateAction::class),
            bulkDelete: $this->withoutConstructor(NotificationsBulkDeleteAction::class),
            bulkActivate: $this->withoutConstructor(NotificationsBulkActivateAction::class),
            bulkResetDisplay: $this->withoutConstructor(NotificationsBulkResetDisplayAction::class),
            bulkRestore: $this->withoutConstructor(NotificationsBulkRestoreAction::class),
        ))->register();

        self::assertSame([
            'create',
            'save',
            'activate',
            'deactivate',
            'delete',
            'restore',
            'reset-display',
            'bulk-deactivate',
            'bulk-delete',
            'bulk-activate',
            'bulk-reset-display',
            'bulk-restore',
        ], array_map(
            static fn(array $action): string => $action['definition']->key(),
            $this->registeredActions($actions),
        ));

        self::assertSame([
            'create' => ['system.notifications.publish', 'notifications.actions.publish', null, true],
            'save' => ['system.notifications.publish', 'admin.common.save', null, true],
            'activate' => ['system.notifications.activate', 'notifications.actions.activate', 'notifications.confirm.activate', true],
            'deactivate' => ['system.notifications.deactivate', 'notifications.actions.deactivate', 'notifications.confirm.deactivate', true],
            'delete' => ['system.notifications.delete', 'notifications.actions.delete', 'notifications.confirm.delete', true],
            'restore' => ['system.notifications.restore', 'notifications.actions.restore', 'notifications.confirm.restore', true],
            'reset-display' => ['system.notifications.reset_display', 'notifications.actions.reset_display', 'notifications.confirm.reset_display', true],
            'bulk-deactivate' => ['system.notifications.deactivate', 'notifications.actions.deactivate', 'notifications.confirm.deactivate', true],
            'bulk-delete' => ['system.notifications.delete', 'notifications.actions.delete', 'notifications.confirm.delete', true],
            'bulk-activate' => ['system.notifications.activate', 'notifications.actions.activate', 'notifications.confirm.activate', true],
            'bulk-reset-display' => ['system.notifications.reset_display', 'notifications.actions.reset_display', 'notifications.confirm.reset_display', true],
            'bulk-restore' => ['system.notifications.restore', 'notifications.actions.restore', 'notifications.confirm.restore_inactive', true],
        ], $this->actionContract($actions));
    }

    /** @return array<string, array{string, string, string|null, bool}> */
    private function actionContract(ModuleActionRegistry $actions): array
    {
        $contract = [];
        foreach ($this->registeredActions($actions) as $action) {
            $definition = $action['definition'];
            $contract[$definition->key()] = [
                $definition->permission(),
                $definition->labelKey(),
                $definition->confirmation()?->messageKey(),
                $definition->refreshGrid(),
            ];
        }

        return $contract;
    }

    /**
     * Vrati verejne dohledatelne akce registrovane pro modul notifikaci
     *
     * @return list<array{definition:\Lemonade\Admin\Action\ModuleActionDefinition,handler:\Lemonade\Admin\Action\Contract\ModuleActionHandlerInterface}>
     */
    private function registeredActions(ModuleActionRegistry $actions): array
    {
        return array_map(
            static fn(string $action): array => $actions->action('system.notifications', $action),
            [
                'create',
                'save',
                'activate',
                'deactivate',
                'delete',
                'restore',
                'reset-display',
                'bulk-deactivate',
                'bulk-delete',
                'bulk-activate',
                'bulk-reset-display',
                'bulk-restore',
            ],
        );
    }

    /** @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function withoutConstructor(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
