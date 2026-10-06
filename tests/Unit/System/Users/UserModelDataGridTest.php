<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\System\Users\Editor\UserEditorInput;
use Lemonade\Admin\System\Users\Models\UserModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserModelDataGridTest extends TestCase
{
    /**
     * @param array<string, string> $filters
     * @param list<string> $expectedConditions
     * @param list<mixed> $expectedBindings
     */
    #[DataProvider('statusFilters')]
    public function testDataGridStatusFilterIsAppliedToCountAndRows(array $filters, array $expectedConditions, array $expectedBindings): void
    {
        $queries = $this->executeDataGridQuery(search: '', filters: $filters);

        self::assertCount(2, $queries);
        foreach ($queries as $query) {
            foreach ($expectedConditions as $expectedCondition) {
                self::assertStringContainsString($expectedCondition, $query['sql']);
            }
            self::assertSame($expectedBindings, $query['bindings']);
        }
    }

    public function testDataGridSearchCombinesWithTheActiveStatusFilter(): void
    {
        $queries = $this->executeDataGridQuery(search: 'admin@example.test', filters: ['status' => 'active']);

        foreach ($queries as $query) {
            self::assertStringContainsString('u.deleted_at IS NULL', $query['sql']);
            self::assertStringContainsString('u.active = ?', $query['sql']);
            self::assertStringContainsString('u.email LIKE ?', $query['sql']);
            self::assertSame(['%admin@example.test%', 1], $query['bindings']);
        }
    }

    public function testVersionedEditorUpdateComparesAndAdvancesTheDedicatedVersionAtomically(): void
    {
        $queries = [];
        $affectedRows = 1;
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = ['sql' => $sql, 'bindings' => $bindings === false ? [] : array_values($bindings)];

            return $this->databaseResult();
        });
        $driver->method('affected_rows')->willReturnCallback(static function () use (&$affectedRows): int {
            return $affectedRows;
        });
        $model = new UserModel($driver);

        self::assertTrue($model->updateEditorVersioned(3, 7, $this->editorInput(7)), 'a freshly rendered version saves successfully');
        self::assertStringContainsString('UPDATE system_user SET', $queries[0]['sql']);
        self::assertStringContainsString('WHERE deleted_at IS NULL AND id = ? AND version = ?', $queries[0]['sql']);
        self::assertContains(8, $queries[0]['bindings'], 'a successful save advances version X to X + 1');
        self::assertSame([3, 7], array_slice($queries[0]['bindings'], -2), 'the update compares the entity ID and rendered version together');

        $affectedRows = 0;
        self::assertFalse($model->updateEditorVersioned(3, 7, $this->editorInput(7)), 'a genuinely stale version is rejected');

        $affectedRows = 1;
        self::assertTrue($model->updateEditorVersioned(3, 8, $this->editorInput(8)), 'the version returned after save is valid for the next save');
    }

    /** @return iterable<string, array{array<string, string>, list<string>, list<mixed>}> */
    public static function statusFilters(): iterable
    {
        yield 'all excludes deleted users' => [[], ['u.deleted_at IS NULL'], []];
        yield 'active includes only active non-deleted users' => [['status' => 'active'], ['u.deleted_at IS NULL', 'u.active = ?'], [1]];
        yield 'inactive includes only inactive non-deleted users' => [['status' => 'inactive'], ['u.deleted_at IS NULL', 'u.active = ?'], [0]];
        yield 'deleted includes only deleted users' => [['status' => 'deleted'], ['u.deleted_at IS NOT NULL'], []];
    }

    /**
     * @param array<string, string> $filters
     * @return list<array{sql:string,bindings:list<mixed>}>
     */
    private function executeDataGridQuery(string $search, array $filters): array
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = ['sql' => $sql, 'bindings' => $bindings === false ? [] : array_values($bindings)];

            return $this->databaseResult();
        });

        (new UserModel($driver))->listForDataGrid(new DataGridQuery(
            page: 1,
            pageSize: 25,
            sortKey: 'email',
            sortDirection: 'asc',
            search: $search,
            filters: $filters,
        ));

        return $queries;
    }

    private function databaseResult(): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('row_array')->willReturn(['numrows' => 0]);
        $result->method('result_array')->willReturn([]);

        return $result;
    }

    private function editorInput(int $version): UserEditorInput
    {
        return new UserEditorInput(
            firstName: 'Ada',
            lastName: 'Lovelace',
            email: 'ada@example.test',
            phone: null,
            active: true,
            version: $version,
            localPassword: null,
            roleId: null,
            permissionStates: null,
        );
    }
}
