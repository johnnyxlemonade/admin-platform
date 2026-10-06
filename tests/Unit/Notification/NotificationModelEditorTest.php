<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Notification;

use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;

final class NotificationModelEditorTest extends TestCase
{
    public function testEditorDataLoadsDirectAudienceUsersWithExplicitAliases(): void
    {
        $notification = $this->databaseResult([[
            'id' => 15,
            'type' => 'info',
            'title' => 'Plánovaná odstávka',
            'message' => 'Zpráva',
            'active' => 1,
            'deleted_at' => null,
        ]]);
        $roles = $this->databaseResult([['role_id' => 4]]);
        $users = $this->databaseResult([['id' => 7, 'email' => 'reader@example.test']]);
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnOnConsecutiveCalls($notification, $roles, $users);

        $data = (new NotificationModel($driver))->notificationForEditor(15);
        if ($data === null) {
            self::fail('Existing notification must provide editor data.');
        }

        self::assertSame([4], $data['role_ids']);
        self::assertSame([7], $data['user_ids']);
        self::assertSame([['id' => 7, 'email' => 'reader@example.test']], $data['audience_users']);
    }

    /** @param list<array<string, mixed>> $rows */
    private function databaseResult(array $rows): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn($rows);

        return $result;
    }
}
