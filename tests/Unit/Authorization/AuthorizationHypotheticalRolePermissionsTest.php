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

/**
 * Overuje hypoteticky vypocet efektivnich opravneni role
 */
final class AuthorizationHypotheticalRolePermissionsTest extends TestCase
{
    /**
     * Overi, ze hypoteticky set nahradi jen prirazenou roli a zamkne vstupni rows
     */
    public function testItCombinesHypotheticalRolePermissionsWithOtherRolePermissions(): void
    {
        $connection = new HypotheticalPermissionConnection([]);
        $authorization = new AuthorizationService($this->principal(), $this->database($connection));

        $permissions = $authorization->effectivePermissionsForUserWithRolePermissions(
            user: new AuthenticatedUser(7, 'editor@example.test'),
            roleId: 11,
            rolePermissionCodes: ['system.roles.edit'],
        );

        self::assertSame(['system.roles.edit', 'system.roles.view'], $permissions);
        self::assertTrue($connection->assignmentsLocked);
        self::assertTrue($connection->overridesLocked);
    }

    /**
     * Overi, ze allow override zachova pravo odebrane z hypoteticke role
     */
    public function testItAppliesUserOverridesAfterHypotheticalRolePermissions(): void
    {
        $connection = new HypotheticalPermissionConnection([
            ['code' => 'system.roles.edit', 'name_key' => 'roles.permissions.edit', 'module_code' => 'system.roles', 'effect' => 'allow'],
        ]);
        $authorization = new AuthorizationService($this->principal(), $this->database($connection));

        $permissions = $authorization->effectivePermissionsForUserWithRolePermissions(
            user: new AuthenticatedUser(7, 'editor@example.test'),
            roleId: 11,
            rolePermissionCodes: [],
        );

        self::assertSame(['system.roles.edit', 'system.roles.view'], $permissions);
    }

    /**
     * Vraci principal provider pro lokalniho uzivatele testu
     */
    private function principal(): CurrentPrincipalProviderInterface
    {
        return new class implements CurrentPrincipalProviderInterface {
            private AuthenticatedUser $user;

            /**
             * Nastavuje lokalni identitu pro vypocet opravneni
             */
            public function __construct()
            {
                $this->user = new AuthenticatedUser(7, 'editor@example.test');
            }

            /**
             * Vraci lokalni admin principal
             */
            public function currentPrincipal(): AdminPrincipalInterface
            {
                return new LocalAdminPrincipal($this->user);
            }

            /**
             * Vraci lokalniho uzivatele
             */
            public function currentUser(): AuthenticatedUser
            {
                return $this->user;
            }
        };
    }

    /**
     * Sestavi databazovy facade nad DB-free connection double
     */
    private function database(ConnectionInterface $connection): Database
    {
        return new Database($connection, $this->createMock(DatabaseDriverInterface::class));
    }
}

/**
 * Simuluje dotazy potrebne pro hypoteticky vypocet opravneni
 */
final class HypotheticalPermissionConnection implements ConnectionInterface
{
    /** @var list<array{code:string,name_key:string,module_code:string,effect:string}> */
    private array $overrides;

    public bool $assignmentsLocked = false;

    public bool $overridesLocked = false;

    /**
     * @param list<array{code:string,name_key:string,module_code:string,effect:string}> $overrides
     */
    public function __construct(array $overrides)
    {
        $this->overrides = $overrides;
    }

    /**
     * Vraci canonical rows pro hypoteticky authorization calculation
     *
     * @param array<int|string,mixed> $bindings
     * @return list<array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        if (str_contains($sql, 'r.is_super_admin=1')) {
            return [];
        }
        if (str_contains($sql, 'SELECT role_id FROM system_user_role')) {
            $this->assignmentsLocked = str_contains($sql, 'FOR UPDATE');

            return [['role_id' => 11]];
        }
        if (str_contains($sql, 'ur.role_id <> ?')) {
            return [['code' => 'system.roles.view', 'name_key' => 'roles.permissions.view', 'module_code' => 'system.roles']];
        }
        if (str_contains($sql, 'SELECT code, name_key, module_code FROM system_permission')) {
            return [
                ['code' => 'system.roles.edit', 'name_key' => 'roles.permissions.edit', 'module_code' => 'system.roles'],
                ['code' => 'system.roles.view', 'name_key' => 'roles.permissions.view', 'module_code' => 'system.roles'],
            ];
        }
        if (str_contains($sql, 'system_user_permission')) {
            $this->overridesLocked = str_contains($sql, 'FOR UPDATE');

            return $this->overrides;
        }

        return [];
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        yield from [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        return 0;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function inTransaction(): bool
    {
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 0;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return 'test';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}
