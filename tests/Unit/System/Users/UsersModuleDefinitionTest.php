<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;
use Lemonade\Admin\System\Users\DataGrid\UsersDataGrid;
use Lemonade\Admin\System\Users\Editor\UsersEditor;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use PHPUnit\Framework\TestCase;

final class UsersModuleDefinitionTest extends TestCase
{
    public function testItProvidesSystemAdminMetadataWithoutLocalizedValues(): void
    {
        $definition = new UsersModuleDefinition();
        $metadata = $definition->adminMetadata();

        self::assertInstanceOf(AdminModuleDefinitionInterface::class, $definition);
        self::assertSame('system.users', $definition->code());
        self::assertSame('users.module.name', $metadata->nameKey());
        self::assertSame(AdminIcon::People, $metadata->icon());
        self::assertSame('system', $metadata->navigationGroup());
        self::assertFalse($metadata->isStandaloneNavigation());
        self::assertSame(45, $metadata->navigationOrder());
        self::assertSame('admin.system.module.index', $metadata->destinationRoute());
        self::assertSame('users', $metadata->routeSegment());
        self::assertSame(['module' => 'users'], $metadata->destinationParameters());
        self::assertSame('system.users.view', $metadata->navigationPermission());
        self::assertSame([
            'users.permissions.view',
            'users.permissions.create',
            'users.permissions.edit',
            'users.permissions.disable',
            'users.permissions.delete',
            'users.permissions.restore',
            'users.permissions.manage_roles',
            'users.permissions.manage_permissions',
        ], array_map(static fn(\Lemonade\Admin\Authorization\PermissionDefinition $permission): string => $permission->nameKey(), $definition->permissionDefinitions()));
        $dataGridInterfaces = class_implements(UsersDataGrid::class);
        $editorInterfaces = class_implements(UsersEditor::class);

        self::assertIsArray($dataGridInterfaces);
        self::assertIsArray($editorInterfaces);
        self::assertContains(\Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface::class, $dataGridInterfaces);
        self::assertContains(EditorProviderInterface::class, $editorInterfaces);
    }
}
