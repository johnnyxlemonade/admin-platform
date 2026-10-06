<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\Editor\AdminEditor\CustomViewBlock;
use Lemonade\Admin\System\Notifications\Editor\NotificationsAdminEditorDefinitionFactory;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class NotificationsAdminEditorDefinitionFactoryTest extends TestCase
{
    public function testModalAudienceRemainsAnExplicitCustomViewBlock(): void
    {
        $editor = $this->factory()->modal(
            notification: $this->notification(),
            roles: $this->roles(),
            input: ['audience_type' => 'users'],
            editing: false,
        );

        $custom = $this->modalAudienceBlock($editor);
        self::assertInstanceOf(CustomViewBlock::class, $custom);
        self::assertSame('notifications::editor.audience', $custom->view());
        self::assertSame(['audience_type' => 'users'], $custom->context()['input']);
        self::assertSame($this->roles(), $custom->context()['roles']);
    }

    public function testModalAudienceKeepsPersistedSelectionsAndOldInput(): void
    {
        $notification = [
            ...$this->notification(),
            'id' => 15,
            'role_ids' => [1],
            'user_ids' => [9],
            'audience_users' => [['id' => 9, 'email' => 'reader@example.test']],
        ];

        $editor = $this->factory()->modal(notification: $notification, roles: $this->roles(), input: [], editing: true);
        $custom = $this->modalAudienceBlock($editor);

        self::assertSame([1], $custom->context()['notification']['role_ids']);
        self::assertSame([9], $custom->context()['notification']['user_ids']);
        self::assertSame([['id' => 9, 'email' => 'reader@example.test']], $custom->context()['selectedUsers']);

        $oldInput = ['audience_type' => 'users', 'user_ids' => ['12']];
        $editor = $this->factory()->modal(notification: $notification, roles: $this->roles(), input: $oldInput, editing: true);

        self::assertSame($oldInput, $this->modalAudienceBlock($editor)->context()['input']);
    }

    public function testAudiencePartialPrefillsStoredRolesAndUsersUnlessOldInputOverridesThem(): void
    {
        $notification = [
            ...$this->notification(),
            'role_ids' => [1],
            'user_ids' => [9],
            'audience_users' => [['id' => 9, 'email' => 'reader@example.test']],
        ];
        $roles = [['id' => 1, 'code' => 'root', 'name' => 'Root', 'is_super_admin' => 1]];

        $rolesHtml = $this->renderAudience($notification, $roles, [], $notification['audience_users']);
        self::assertStringContainsString('value="roles" checked', $rolesHtml);
        self::assertStringContainsString('value="1" selected>Root</option>', $rolesHtml);

        $usersHtml = $this->renderAudience($notification, $roles, ['audience_type' => 'users', 'role_ids' => [], 'user_ids' => ['9']], $notification['audience_users']);
        self::assertStringContainsString('value="users" checked', $usersHtml);
        self::assertStringContainsString('value="9" selected>reader@example.test</option>', $usersHtml);
        self::assertStringNotContainsString('value="1" selected>Root</option>', $usersHtml);
    }

    /** @return array<string, mixed> */
    private function notification(): array
    {
        return [
            'type' => 'info',
            'title' => '',
            'message' => '',
            'audience_type' => 'roles',
            'role_ids' => [],
            'user_ids' => [],
        ];
    }

    /** @return list<array{id:int,code:string,name:string,is_super_admin:int}> */
    private function roles(): array
    {
        return [['id' => 7, 'code' => 'editor', 'name' => 'Editor', 'is_super_admin' => 0]];
    }

    private function factory(): NotificationsAdminEditorDefinitionFactory
    {
        $router = new Router();
        $router->getNamed('admin.module.index', '/admin/{module}', ControllerAction::for('TestController', 'index'));
        $router->getNamed('admin.system.module.index', '/admin/system/{module}', ControllerAction::for('TestController', 'index'));
        $router->getNamed('admin.system.module.create', '/admin/system/{module}/create', ControllerAction::for('TestController', 'create'));
        $router->getNamed('admin.system.module.edit', '/admin/system/{module}/edit/{id}', ControllerAction::for('TestController', 'edit'));
        $router->postNamed('admin.system.module.ajax.create', '/admin/system/{module}/ajax', ControllerAction::for('TestController', 'create'));
        $router->postNamed('admin.system.module.ajax.entity', '/admin/system/{module}/ajax/{id}', ControllerAction::for('TestController', 'entity'));
        $router->getNamed('admin.module.create', '/admin/{module}/create', ControllerAction::for('TestController', 'create'));
        $router->getNamed('admin.module.edit', '/admin/{module}/edit/{id}', ControllerAction::for('TestController', 'edit'));
        $router->postNamed('admin.module.ajax.create', '/admin/{module}/ajax', ControllerAction::for('TestController', 'create'));
        $router->postNamed('admin.module.ajax.entity', '/admin/{module}/ajax/{id}', ControllerAction::for('TestController', 'entity'));

        return new NotificationsAdminEditorDefinitionFactory(urls: new UrlGenerator(router: $router));
    }

    private function modalAudienceBlock(\Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition $editor): CustomViewBlock
    {
        $block = $editor->blocks()[2];
        self::assertInstanceOf(CustomViewBlock::class, $block);

        return $block;
    }

    /**
     * @param array<string, mixed> $notification
     * @param list<array{id:int,code:string,name:string,is_super_admin:int}> $roles
     * @param array<string, mixed> $input
     * @param list<array{id:int,email:string}> $selectedUsers
     */
    private function renderAudience(array $notification, array $roles, array $input, array $selectedUsers): string
    {
        $helpers = new class {
            public function lang(string $key): string
            {
                return $key;
            }

            public function url(string $name): string
            {
                return '/admin/notification-management/audience/users';
            }
        };

        ob_start();
        include dirname(__DIR__, 4) . '/src/System/Notifications/Resources/views/editor/audience.php';

        return (string) ob_get_clean();
    }
}
