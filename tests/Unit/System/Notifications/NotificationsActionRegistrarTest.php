<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\System\Notifications\Actions\NotificationsActionRegistrar;
use PHPUnit\Framework\TestCase;

final class NotificationsActionRegistrarTest extends TestCase
{
    public function testRegistersTheNotificationActionContract(): void
    {
        $actions = new ModuleActionRegistry();

        (new NotificationsActionRegistrar(
            actions: $actions,
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
     * @return list<array{definition:\Lemonade\Admin\Action\ModuleActionDefinition,handlerServiceId:string}>
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
}
