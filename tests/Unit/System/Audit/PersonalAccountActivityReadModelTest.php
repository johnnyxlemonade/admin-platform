<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Audit;

use Lemonade\Admin\System\Audit\Models\PersonalAccountActivityReadModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the safe personal account activity projection
 */
final class PersonalAccountActivityReadModelTest extends TestCase
{
    /**
     * Verifies timeline filtering, ordering, limits, and safe projected fields
     */
    public function testRecentProjectionIsBoundedToTheTargetUserAndDoesNotExposePayload(): void
    {
        /**
         * @var list<array{0: string, 1: array<array-key, mixed>}> $queries
         */
        $queries = [];
        $driver = self::createStub(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = [$sql, $bindings === false ? [] : $bindings];

            return $this->databaseResult([[
                'event_code' => 'system.users.updated',
                'actor_type' => 'user',
                'actor_key' => '7',
                'actor_user_id' => 7,
                'actor_first_name' => 'Admin',
                'actor_last_name' => 'User',
                'actor_email' => 'admin@example.test',
                'created_at' => '2026-11-27 09:00:00',
                'payload' => '{"secret":"must-not-leak"}',
            ]]);
        });

        $events = (new PersonalAccountActivityReadModel($driver))->recent(42, 500);

        self::assertCount(1, $events);
        self::assertArrayNotHasKey('payload', $events[0]);
        self::assertSame('system.users.updated', $events[0]['event_code']);
        self::assertStringContainsString('a.entity_type', $queries[0][0]);
        self::assertStringContainsString('a.entity_key', $queries[0][0]);
        self::assertStringContainsString('a.event_code IN', $queries[0][0]);
        self::assertStringContainsString('LIMIT 50', $queries[0][0]);
        self::assertContains('42', $queries[0][1]);
    }

    /**
     * Verifies metadata timestamps use only explicit supported events
     */
    public function testMetadataUsesOnlyExplicitSupportedEventCodes(): void
    {
        $driver = self::createStub(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings): DatabaseResultInterface {
            $timestamp = is_array($bindings) && in_array('system.users.password_changed', $bindings, true)
                ? '2026-11-27 10:00:00'
                : '2026-11-26 09:00:00';

            return $this->databaseResult([['created_at' => $timestamp]]);
        });
        $model = new PersonalAccountActivityReadModel($driver);

        self::assertSame('2026-11-26 09:00:00', $model->latestProfileUpdateAt(42));
        self::assertSame('2026-11-27 10:00:00', $model->latestPasswordChangeAt(42));
    }

    /**
     * Creates a database result with prepared rows
     *
     * @param list<array<string, mixed>> $rows
     */
    private function databaseResult(array $rows): DatabaseResultInterface
    {
        $result = self::createStub(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn($rows);
        $result->method('num_rows')->willReturn(count($rows));

        return $result;
    }
}
