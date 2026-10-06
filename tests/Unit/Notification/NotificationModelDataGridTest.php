<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Notification;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationModelDataGridTest extends TestCase
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
        $queries = $this->executeDataGridQuery(search: 'maintenance', filters: ['status' => 'active']);
        $like = '%maintenance%';

        foreach ($queries as $query) {
            self::assertStringContainsString('n.deleted_at IS NULL', $query['sql']);
            self::assertStringContainsString('n.active = ?', $query['sql']);
            self::assertStringContainsString('n.title LIKE ?', $query['sql']);
            self::assertStringContainsString('n.message LIKE ?', $query['sql']);
            self::assertStringContainsString('u.email LIKE ?', $query['sql']);
            self::assertStringContainsString('admin_notification_audience_role', $query['sql']);
            self::assertStringContainsString('admin_notification_audience_user', $query['sql']);
            self::assertSame([1, $like, $like, $like, $like, $like, $like, $like, $like], $query['bindings']);
        }
    }

    /** @return iterable<string, array{array<string, string>, list<string>, list<mixed>}> */
    public static function statusFilters(): iterable
    {
        yield 'all excludes deleted notifications' => [[], ['n.deleted_at IS NULL'], []];
        yield 'active includes only active non-deleted notifications' => [['status' => 'active'], ['n.deleted_at IS NULL', 'n.active = ?'], [1]];
        yield 'inactive includes only inactive non-deleted notifications' => [['status' => 'inactive'], ['n.deleted_at IS NULL', 'n.active = ?'], [0]];
        yield 'deleted includes only deleted notifications' => [['status' => 'deleted'], ['n.deleted_at IS NOT NULL'], []];
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

        (new NotificationModel($driver))->listForDataGrid(new DataGridQuery(
            page: 1,
            pageSize: 25,
            sortKey: 'createdAt',
            sortDirection: 'desc',
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
}
