<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Roles;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\System\Roles\Models\RoleModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoleModelDataGridTest extends TestCase
{
    /**
     * @param array<string, string> $filters
     */
    #[DataProvider('statusFilters')]
    public function testDataGridQualifiesTheRoleSoftDeleteStatusFilter(array $filters, string $expectedCondition): void
    {
        $queries = $this->executeDataGridQuery($filters);

        self::assertCount(2, $queries);
        $rowQuery = null;
        foreach ($queries as $query) {
            self::assertStringContainsString('FROM system_role AS r', $query['sql']);
            self::assertStringContainsString($expectedCondition, $query['sql']);
            self::assertStringNotContainsString('WHERE deleted_at', $query['sql']);
            if (str_contains($query['sql'], 'LEFT JOIN system_user_role ur')) {
                $rowQuery = $query['sql'];
            }
        }
        self::assertIsString($rowQuery);
        self::assertStringContainsString('LEFT JOIN system_user_role ur ON ur.role_id = r.id', $rowQuery);
    }

    public function testDataGridAppliesTheSelectedSortBeforeItsDeterministicTieBreaker(): void
    {
        $queries = $this->executeDataGridQuery([], 'code', 'desc');

        $rowQuery = null;
        foreach ($queries as $query) {
            if (str_contains($query['sql'], 'LEFT JOIN system_user_role ur')) {
                $rowQuery = $query['sql'];
            }
        }

        self::assertIsString($rowQuery);
        self::assertStringContainsString('ORDER BY r.code DESC, r.id ASC', $rowQuery);
        self::assertStringNotContainsString('role_group', $rowQuery);
        self::assertStringNotContainsString('canonical_role_order', $rowQuery);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function statusFilters(): iterable
    {
        yield 'active roles' => [['status' => 'active'], 'r.deleted_at IS NULL'];
        yield 'deleted roles' => [['status' => 'deleted'], 'r.deleted_at IS NOT NULL'];
    }

    /**
     * @param array<string, string> $filters
     * @return list<array{sql:string,bindings:list<mixed>}>
     */
    private function executeDataGridQuery(array $filters, string $sortKey = 'name', string $sortDirection = 'asc'): array
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = ['sql' => $sql, 'bindings' => $bindings === false ? [] : array_values($bindings)];

            return $this->databaseResult();
        });

        (new RoleModel($driver))->listForDataGrid(new DataGridQuery(
            page: 1,
            pageSize: 100,
            sortKey: $sortKey,
            sortDirection: $sortDirection,
            search: '',
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
}
