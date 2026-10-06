<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Notification;

use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;

/**
 * Overuje davkovou reportni projekci oznameni
 */
final class NotificationModelExportTest extends TestCase
{
    /**
     * Overi, ze export nacte radky a publikum tremi davkovymi dotazy
     */
    public function testItStreamsSelectedRowsWithBatchAudienceSummaries(): void
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $queryNumber = 0;
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries, &$queryNumber): DatabaseResultInterface {
            $queries[] = ['sql' => $sql, 'bindings' => $bindings === false ? [] : array_values($bindings)];
            $queryNumber++;

            if ($queryNumber === 1) {
                return $this->databaseResult([
                    ['id' => 2, 'title' => 'Udrzba', 'active' => 1, 'deleted_at' => null, 'created_at' => '2026-10-02 10:00:00', 'updated_at' => '2026-10-02 10:15:00', 'author_first_name' => 'Ada', 'author_last_name' => 'Admin', 'author_email' => 'ada@example.test'],
                    ['id' => 7, 'title' => 'Archiv', 'active' => 0, 'deleted_at' => '2026-10-02 11:00:00', 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-02 11:00:00', 'author_first_name' => null, 'author_last_name' => null, 'author_email' => 'author@example.test'],
                ]);
            }
            if ($queryNumber === 2) {
                return $this->databaseResult([
                    ['notification_id' => 2, 'role_name' => 'Editors'],
                ]);
            }

            return $this->databaseResult([
                ['notification_id' => 7, 'user_email' => 'user@example.test'],
            ]);
        });

        $rows = iterator_to_array((new NotificationModel($driver))->iterateExportRows([2, 7]));

        self::assertSame('roles:Editors', $rows[0]['audience']);
        self::assertSame('users:user@example.test', $rows[1]['audience']);
        self::assertCount(3, $queries);
        self::assertSame([2, 7], $queries[0]['bindings']);
        self::assertSame([2, 7], $queries[1]['bindings']);
        self::assertSame([2, 7], $queries[2]['bindings']);
    }

    /**
     * Vytvori vysledek databazoveho dotazu z predanych radku
     *
     * @param list<array<string, int|string|null>> $rows
     */
    private function databaseResult(array $rows): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn($rows);

        return $result;
    }
}
