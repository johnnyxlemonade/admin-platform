<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Authorization;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

final class AuthorizationRequestCacheTest extends TestCase
{
    public function testRepeatedChecksReuseSuperAdminRoleAndOverrideReads(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::exactly(3))->method('select')->willReturnCallback(
            static function (string $sql): array {
                if (str_contains($sql, 'r.is_super_admin=1')) {
                    return [];
                }
                if (str_contains($sql, 'system_role_permission')) {
                    return [['code' => 'system.users.view', 'name_key' => 'users.permissions.view', 'module_code' => 'system.users']];
                }

                return [];
            },
        );
        $authorization = new AuthorizationService($this->principal(42), $this->database($connection));

        self::assertTrue($authorization->hasPermission('system.users.view'));
        self::assertTrue($authorization->hasPermission('system.users.view'));
        self::assertFalse($authorization->hasPermission('system.audit.view'));
    }

    public function testExplicitInvalidationRefreshesPermissionDataAfterMutation(): void
    {
        $permissionState = new MutablePermissionState();
        $selects = 0;
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(
            static function (string $sql) use ($permissionState, &$selects): array {
                ++$selects;
                if (str_contains($sql, 'r.is_super_admin=1')) {
                    return [];
                }
                if (str_contains($sql, 'system_role_permission')) {
                    return $permissionState->rows;
                }

                return [];
            },
        );
        $authorization = new AuthorizationService($this->principal(42), $this->database($connection));

        self::assertFalse($authorization->hasPermission('system.audit.view'));
        $permissionState->rows = [['code' => 'system.audit.view', 'name_key' => 'audit.permissions.view', 'module_code' => 'system.audit']];
        self::assertFalse($authorization->hasPermission('system.audit.view'));
        self::assertSame(3, $selects);

        $authorization->invalidate();

        self::assertTrue($authorization->hasPermission('system.audit.view'));
        self::assertSame(6, $selects);
    }

    public function testSeparateServiceInstancesDoNotShareCachedAuthorizationData(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::exactly(6))->method('select')->willReturnCallback(
            static function (string $sql): array {
                if (str_contains($sql, 'r.is_super_admin=1')) {
                    return [];
                }

                return [];
            },
        );

        self::assertFalse((new AuthorizationService($this->principal(42), $this->database($connection)))->hasPermission('system.audit.view'));
        self::assertFalse((new AuthorizationService($this->principal(42), $this->database($connection)))->hasPermission('system.audit.view'));
    }

    private function principal(int $id): CurrentPrincipalProviderInterface
    {
        return new class ($id) implements CurrentPrincipalProviderInterface {
            private AuthenticatedUser $user;

            public function __construct(int $id)
            {
                $this->user = new AuthenticatedUser($id, 'admin@example.test');
            }

            public function currentPrincipal(): AdminPrincipalInterface
            {
                return new LocalAdminPrincipal($this->user);
            }

            public function currentUser(): AuthenticatedUser
            {
                return $this->user;
            }
        };
    }

    private function database(ConnectionInterface $connection): Database
    {
        return new Database($connection, $this->createMock(DatabaseDriverInterface::class));
    }
}

final class MutablePermissionState
{
    /** @var list<array{code:string,name_key:string,module_code:string}> */
    public array $rows = [];
}
