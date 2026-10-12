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
        $router->getNamed('admin.system.module.index', '/admin/system/{module}', ControllerAction::for('TestController', 'index'));
        $router->getNamed('admin.system.module.create', '/admin/system/{module}/create', ControllerAction::for('TestController', 'create'));
        $router->getNamed('admin.system.module.edit', '/admin/system/{module}/edit/{id}', ControllerAction::for('TestController', 'edit'));
        $router->postNamed('admin.system.module.ajax.create', '/admin/system/{module}/ajax', ControllerAction::for('TestController', 'create'));
        $router->postNamed('admin.system.module.ajax.entity', '/admin/system/{module}/ajax/{id}', ControllerAction::for('TestController', 'entity'));

        return new NotificationsAdminEditorDefinitionFactory(urls: new UrlGenerator(router: $router));
    }

    private function modalAudienceBlock(\Lemonade\Admin\Editor\AdminEditor\AdminEditorDefinition $editor): CustomViewBlock
    {
        $block = $editor->blocks()[2];
        self::assertInstanceOf(CustomViewBlock::class, $block);

        return $block;
    }
}
