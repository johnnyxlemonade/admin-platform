<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\System\Users\Models\UserRoleModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

final class CanonicalAdminPermissionsTest extends TestCase
{
    public function testAdministratorReceivesTheCurrentSystemManagementPermissions(): void
    {
        $queries = [];
        $database = $this->createMock(DatabaseDriverInterface::class);
        $database
            ->method('query')
            ->willReturnCallback(static function (string $sql, array|false $bindings = false) use (&$queries): bool {
                $queries[] = ['sql' => $sql, 'bindings' => $bindings];

                return true;
            });

        (new UserRoleModel($database))->synchronizeCanonicalSystemRolePermissions();

        $adminPermissions = array_values(array_filter(
            $queries,
            static fn(array $query): bool => str_contains($query['sql'], 'INSERT INTO system_role_permission'),
        ));

        self::assertCount(1, $adminPermissions);
        self::assertSame([
            'system.users.view',
            'system.users.create',
            'system.users.edit',
            'system.users.disable',
            'system.users.delete',
            'system.users.restore',
            'system.users.manage_roles',
            'system.users.manage_permissions',
            'system.audit.view',
            'system.notifications.view',
            'system.notifications.publish',
            'system.notifications.activate',
            'system.notifications.deactivate',
            'system.notifications.delete',
            'system.notifications.restore',
            'system.notifications.reset_display',
            'system.languages.view',
            'system.languages.create',
            'system.languages.edit',
            'system.languages.enable',
            'system.languages.disable',
            'system.languages.set_default',
            'system.roles.view',
            'system.roles.create',
            'system.roles.edit',
            'system.roles.delete',
            'system.roles.restore',
            'system.media.view',
            'admin',
        ], $adminPermissions[0]['bindings']);
    }
}
