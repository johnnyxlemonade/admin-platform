<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Authorization;

use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionSyncService;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

final class PermissionSyncServiceTest extends TestCase
{
    public function testSyncRegistersAvailablePermissionsWithoutGrantingThemToAdministrator(): void
    {
        $state = new PermissionSyncState();
        $catalog = $this->catalog(
            new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'),
            new PermissionDefinition('cms.news.edit', 'cms.news', 'news.permissions.edit'),
            new PermissionDefinition('system.modules.view', 'system.modules', 'modules.permissions.view'),
        );

        (new PermissionSyncService($catalog, $this->database($state)))->sync();

        self::assertSame([], $state->administratorPermissions());
        $permissionCodes = array_keys($state->permissions);
        sort($permissionCodes);
        self::assertSame(['cms.news.edit', 'system.modules.view', 'system.users.view'], $permissionCodes);
    }

    public function testSyncLeavesExistingRoleAssignmentsUnchangedWhenPermissionIsAdded(): void
    {
        $state = new PermissionSyncState([
            ['code' => 'system.users.view', 'module_code' => 'system.users', 'name_key' => 'users.permissions.view'],
        ], ['system.users.view']);
        $catalog = $this->catalog(
            new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'),
            new PermissionDefinition('system.media.view', 'system.media', 'media.permissions.view'),
        );
        $service = new PermissionSyncService($catalog, $this->database($state));

        $service->sync();

        self::assertSame(['system.users.view'], $state->administratorPermissions());
        $roleMutationsAfterFirstSync = $state->administratorMutationCount();

        $service->sync();

        self::assertSame($roleMutationsAfterFirstSync, $state->administratorMutationCount());
    }

    public function testSyncReportsStalePermissionWithoutDeletingStoredAssignments(): void
    {
        $state = new PermissionSyncState([
            ['code' => 'system.users.view', 'module_code' => 'system.users', 'name_key' => 'users.permissions.view'],
            ['code' => 'system.media.legacy', 'module_code' => 'system.media', 'name_key' => 'media.permissions.legacy'],
        ], ['system.media.legacy', 'system.users.view']);
        $catalog = $this->catalog(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));

        $report = (new PermissionSyncService($catalog, $this->database($state)))->sync();

        self::assertSame(['system.media.legacy'], $report->stale());
        self::assertSame(['system.media.legacy', 'system.users.view'], $state->administratorPermissions());
        self::assertArrayHasKey('system.media.legacy', $state->permissions);
    }

    public function testSyncRetainsTranslationKeysInsteadOfLocaleSpecificLabels(): void
    {
        $state = new PermissionSyncState();
        $catalog = $this->catalog(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));

        (new PermissionSyncService($catalog, $this->database($state)))->sync();

        self::assertSame('users.permissions.view', $state->permissions['system.users.view']['name_key']);
    }

    private function catalog(PermissionDefinition ...$definitions): PermissionCatalogRegistry
    {
        $catalog = new PermissionCatalogRegistry();
        foreach ($definitions as $definition) {
            $catalog->register($definition);
        }

        return $catalog;
    }

    private function database(PermissionSyncState $state): Database
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback($state->select(...));
        $connection->method('statement')->willReturnCallback($state->statement(...));

        return new Database($connection, $this->createMock(DatabaseDriverInterface::class));
    }
}

final class PermissionSyncState
{
    /** @var array<string, array{code:string,module_code:string,name_key:string}> */
    public array $permissions = [];

    /** @var array<string, true> */
    private array $administratorAssignments = [];

    private int $administratorMutations = 0;

    /**
     * @param list<array{code:string,module_code:string,name_key:string}> $permissions
     * @param list<string> $administratorAssignments
     */
    public function __construct(array $permissions = [], array $administratorAssignments = [])
    {
        foreach ($permissions as $permission) {
            $this->permissions[$permission['code']] = $permission;
        }
        foreach ($administratorAssignments as $code) {
            $this->administratorAssignments[$code] = true;
        }
    }

    /**
     * @param list<string> $bindings
     * @return list<array<string, string>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        if ($sql === 'SELECT code,module_code,name_key FROM system_permission') {
            return array_values($this->permissions);
        }
        return [];
    }

    /** @param list<string> $bindings */
    public function statement(string $sql, array $bindings = []): int
    {
        if ($sql === 'INSERT INTO system_permission (code,module_code,name_key) VALUES (?,?,?)') {
            $this->permissions[$bindings[0]] = ['code' => $bindings[0], 'module_code' => $bindings[1], 'name_key' => $bindings[2]];
        } elseif ($sql === 'UPDATE system_permission SET module_code=?,name_key=? WHERE code=?') {
            $this->permissions[$bindings[2]] = ['code' => $bindings[2], 'module_code' => $bindings[0], 'name_key' => $bindings[1]];
        } elseif ($sql === 'DELETE FROM system_permission WHERE code IN (?)') {
            foreach ($bindings as $code) {
                unset($this->permissions[$code], $this->administratorAssignments[$code]);
            }
        } elseif (str_starts_with($sql, 'DELETE assignments FROM system_role_permission assignments INNER JOIN system_permission permission')) {
            foreach ($bindings as $code) {
                unset($this->administratorAssignments[$code]);
            }
        }

        return 1;
    }

    /** @return list<string> */
    public function administratorPermissions(): array
    {
        $permissions = array_keys($this->administratorAssignments);
        sort($permissions);

        return $permissions;
    }

    public function administratorMutationCount(): int
    {
        return $this->administratorMutations;
    }
}
