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
    public function testItInsertsTranslationKeysInsteadOfLocaleSpecificLabels(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->with('SELECT code,module_code,name_key FROM system_permission')->willReturn([]);
        $connection->expects(self::once())->method('statement')->with(
            'INSERT INTO system_permission (code,module_code,name_key) VALUES (?,?,?)',
            ['system.users.view', 'system.users', 'users.permissions.view'],
        )->willReturn(1);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));

        $report = (new PermissionSyncService($catalog, $this->database($connection)))->sync();

        self::assertSame(1, $report->inserted());
    }

    public function testItUpdatesAChangedTranslationKey(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->with('SELECT code,module_code,name_key FROM system_permission')->willReturn([
            ['code' => 'system.users.view', 'module_code' => 'system.users', 'name_key' => 'users.permissions.old_view'],
        ]);
        $connection->expects(self::once())->method('statement')->with(
            'UPDATE system_permission SET module_code=?,name_key=? WHERE code=?',
            ['system.users', 'users.permissions.view', 'system.users.view'],
        )->willReturn(1);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));

        $report = (new PermissionSyncService($catalog, $this->database($connection)))->sync();

        self::assertSame(1, $report->updated());
    }

    public function testItRemovesStalePermissionsWithTheirAssignments(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->with('SELECT code,module_code,name_key FROM system_permission')->willReturn([
            ['code' => 'system.media.remove', 'module_code' => 'system.media', 'name_key' => 'media.permissions.remove'],
        ]);
        $statements = [];
        $connection->expects(self::exactly(3))->method('statement')->willReturnCallback(
            static function (string $sql, array $bindings) use (&$statements): int {
                $statements[] = [$sql, $bindings];

                return 1;
            },
        );
        $report = (new PermissionSyncService(new PermissionCatalogRegistry(), $this->database($connection)))->sync();

        self::assertSame(['system.media.remove'], $report->stale());
        self::assertSame([
            ['DELETE assignments FROM system_role_permission assignments INNER JOIN system_permission permission ON permission.id = assignments.permission_id WHERE permission.code IN (?)', ['system.media.remove']],
            ['DELETE assignments FROM system_user_permission assignments INNER JOIN system_permission permission ON permission.id = assignments.permission_id WHERE permission.code IN (?)', ['system.media.remove']],
            ['DELETE FROM system_permission WHERE code IN (?)', ['system.media.remove']],
        ], $statements);
    }

    /**
     * Instalace modulu doplni jen jeho prava a nema destruktivni side effect na chybejici package
     */
    public function testItSynchronizesOneInstalledModuleWithoutRemovingOtherPermissions(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->with('SELECT code,module_code,name_key FROM system_permission WHERE module_code=?', ['cms.news'])->willReturn([]);
        $connection->expects(self::once())->method('statement')->with('INSERT INTO system_permission (code,module_code,name_key) VALUES (?,?,?)', ['cms.news.view', 'cms.news', 'news.permissions.view'])->willReturn(1);
        $catalog = new PermissionCatalogRegistry();
        $catalog->register(new PermissionDefinition('cms.news.view', 'cms.news', 'news.permissions.view'));
        $catalog->register(new PermissionDefinition('system.users.view', 'system.users', 'users.permissions.view'));

        $report = (new PermissionSyncService($catalog, $this->database($connection)))->syncModule('cms.news');

        self::assertSame(1, $report->inserted());
        self::assertSame([], $report->stale());
    }

    private function database(ConnectionInterface $connection): Database
    {
        return new Database($connection, $this->createMock(DatabaseDriverInterface::class));
    }
}
