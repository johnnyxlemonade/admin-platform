<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionIcon;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\NotificationPresentation;
use Lemonade\Admin\System\Notifications\DataGrid\NotificationsDataGrid;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class NotificationsDataGridActionsContractTest extends TestCase
{
    public function testActiveNotificationHasModalEditAndRelevantMutationActions(): void
    {
        $actions = $this->actions(active: true, deleted: false, canPublish: true, availableActions: ['deactivate', 'reset-display', 'delete']);

        self::assertSame(['edit', 'deactivate', 'reset-display', 'delete'], array_column($actions, 'key'));
        self::assertSame('modal', $actions[0]['kind']);
        self::assertSame('secondary', $actions[0]['placement']);
        self::assertSame('/admin/api/modal/notifications/15/edit', $actions[0]['modalUrl']);
        self::assertSame('pencil-square', $actions[0]['icon']);
        self::assertSame('pause-circle', $actions[1]['icon']);
        self::assertSame('arrow-counterclockwise', $actions[2]['icon']);
        self::assertSame('trash3', $actions[3]['icon']);
    }

    public function testInactiveNotificationHasActivateAndResetDisplayActions(): void
    {
        $actions = $this->actions(active: false, deleted: false, canPublish: true, availableActions: ['activate', 'reset-display', 'delete']);

        self::assertSame(['edit', 'activate', 'reset-display', 'delete'], array_column($actions, 'key'));
        self::assertSame('check-circle', $actions[1]['icon']);
        self::assertSame('arrow-counterclockwise', $actions[2]['icon']);
    }

    public function testDeletedNotificationHasOnlyRestoreAction(): void
    {
        $actions = $this->actions(active: true, deleted: true, canPublish: true, availableActions: ['restore', 'reset-display']);

        self::assertSame(['restore'], array_column($actions, 'key'));
        self::assertSame('arrow-counterclockwise', $actions[0]['icon']);
    }

    public function testNotificationWithoutAvailablePermissionsHasNoActions(): void
    {
        self::assertSame([], $this->actions(active: true, deleted: false, canPublish: false, availableActions: []));
    }

    public function testBulkActionsAreLimitedToTheirLifecycleViews(): void
    {
        $grid = new NotificationsDataGrid(
            model: (new ReflectionClass(NotificationModel::class))->newInstanceWithoutConstructor(),
            presentation: (new ReflectionClass(NotificationPresentation::class))->newInstanceWithoutConstructor(),
            translator: $this->translator(),
            actionPresentation: $this->actionPresentation([]),
            authorization: $this->authorization(true),
            urls: $this->urls(),
        );

        $bulkActions = $grid->dataGridDefinition()->bulkActions();

        self::assertSame([
            'bulk-deactivate' => ['active'],
            'bulk-reset-display' => ['active'],
            'bulk-activate' => ['inactive'],
            'bulk-restore' => ['deleted'],
            'bulk-delete' => ['active', 'inactive'],
            'export' => ['all', 'active', 'inactive', 'deleted'],
        ], array_column(array_map(static fn($action): array => [
            'action' => $action->action(),
            'views' => $action->allowedViews(),
        ], $bulkActions), 'views', 'action'));
        self::assertSame([
            'bulk-deactivate',
            'bulk-reset-display',
            'bulk-activate',
            'bulk-restore',
            'bulk-delete',
            'export',
        ], array_map(static fn($action): string => $action->action(), $bulkActions));
        self::assertTrue($grid->dataGridDefinition()->selectionEnabled());
        self::assertTrue($grid->dataGridDefinition()->bulkActions()[5]->download());
    }

    /** @param list<string> $availableActions
     * @return list<array<string, mixed>>
     */
    private function actions(bool $active, bool $deleted, bool $canPublish, array $availableActions): array
    {
        $grid = new NotificationsDataGrid(
            model: (new ReflectionClass(NotificationModel::class))->newInstanceWithoutConstructor(),
            presentation: (new ReflectionClass(NotificationPresentation::class))->newInstanceWithoutConstructor(),
            translator: $this->translator(),
            actionPresentation: $this->actionPresentation($availableActions),
            authorization: $this->authorization($canPublish),
            urls: $this->urls(),
        );
        $actions = new ReflectionMethod($grid, 'actions');
        $result = $actions->invoke($grid, 15, $active, $deleted);

        self::assertIsArray($result);

        return array_values(array_map(static fn(DataGridRowActionDefinition $action): array => $action->toArray(), $result));
    }

    private function translator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('get')->willReturnCallback(static fn(string $key): string => $key);

        return $translator;
    }

    private function authorization(bool $canPublish): AuthorizationService
    {
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturn($canPublish ? [['id' => 1]] : []);
        if ($canPublish) {
            $user = new AuthenticatedUser(7, 'admin@example.test');
            $principals->method('currentUser')->willReturn($user);
            $principals->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($user));
        } else {
            $principals->method('currentUser')->willReturn(null);
            $principals->method('currentPrincipal')->willReturn(null);
        }

        return new AuthorizationService(
            currentUser: $principals,
            database: new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
        );
    }

    /** @param list<string> $availableActions */
    private function actionPresentation(array $availableActions): ModuleActionPresentationFactory
    {
        $presentation = $this->createMock(ModuleActionPresentationFactory::class);
        $presentation->method('rowAction')->willReturnCallback(static function (string $moduleCode, string $action, int $entityId, DataGridRowActionPlacement $placement) use ($availableActions): ?DataGridRowActionDefinition {
            if ($moduleCode !== 'system.notifications' || !in_array($action, $availableActions, true)) {
                return null;
            }

            return (new DataGridRowActionDefinition(
                key: $action,
                label: $action,
                url: '/admin/system/notifications/ajax/' . $entityId,
                method: 'POST',
                kind: DataGridRowActionKind::Mutation,
                placement: $placement,
                refresh: true,
            ))->withIcon(DataGridRowActionIcon::forKey($action) ?? throw new \LogicException('Missing action icon.'));
        });

        return $presentation;
    }

    private function urls(): UrlGenerator
    {
        $router = new Router();
        $router->getNamed('admin.api.modal.edit', '/admin/api/modal/{module}/{id}/edit', ControllerAction::for('EditorModalController', 'edit'));
        $router->postNamed('admin.system.module.ajax.create', '/admin/system/{module}/ajax', ControllerAction::for('ModuleActionController', 'create'));
        $router->postNamed('admin.notifications.export', '/admin/system/notifications/export', ControllerAction::for('NotificationsExportController', 'export'));

        return new UrlGenerator($router);
    }
}
