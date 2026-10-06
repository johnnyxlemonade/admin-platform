<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Notification;

use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\NotificationType;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotificationUpdatedTimestampContractTest extends TestCase
{
    public function testCreateSetsCreatedAndUpdatedTimestampsToTheSameValue(): void
    {
        /** @var list<mixed> $bindings */
        $bindings = [];
        /** @var DatabaseDriverInterface&MockObject $driver */
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $this->configureDriver($driver, static function (string $sql, array|false $values) use (&$bindings): bool {
            if (is_array($values)) {
                $bindings = $values;
            }

            return str_starts_with($sql, 'INSERT INTO');
        });
        $driver->method('insert_id')->willReturn(15);

        $id = (new NotificationModel($driver))->createNotification(
            NotificationType::Info,
            'Plánovaná odstávka',
            'Zpráva',
            7,
        );

        self::assertSame(15, $id);
        self::assertCount(7, $bindings);
        self::assertSame($bindings[5], $bindings[6]);
    }

    public function testEditUpdatesTimestampButResetDisplayOnlyDeletesRecipientState(): void
    {
        /** @var list<array{0:string,1:array<int|string, mixed>}> $queries */
        $queries = [];
        /** @var DatabaseDriverInterface&MockObject $driver */
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $this->configureDriver($driver, static function (string $sql, array|false $values) use (&$queries): bool {
            $queries[] = [$sql, is_array($values) ? $values : []];

            return true;
        });
        $model = new NotificationModel($driver);

        self::assertTrue($model->updateNotification(15, NotificationType::Warning, 'Aktualizace', 'Zpráva'));
        $model->resetDisplayState(15);

        self::assertStringContainsString('updated_at', $queries[0][0]);
        self::assertStringContainsString('UPDATE', $queries[0][0]);
        self::assertStringContainsString('DELETE FROM', $queries[1][0]);
        self::assertStringContainsString('admin_notification_recipient', $queries[1][0]);
        self::assertStringNotContainsString('admin_notification` SET', $queries[1][0]);
    }

    /** @param callable(string, array<int|string, mixed>|false): bool $query */
    private function configureDriver(DatabaseDriverInterface&MockObject $driver, callable $query): void
    {
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback($query);
    }
}
