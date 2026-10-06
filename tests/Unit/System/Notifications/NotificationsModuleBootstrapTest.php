<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Notifications;

use Lemonade\Admin\System\Notifications\Migrations\RegisterNotificationsModule;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class NotificationsModuleBootstrapTest extends TestCase
{
    public function testRegistrationMigrationSeedsTheSystemNotificationModule(): void
    {
        $database = $this->createMock(DatabaseDriverInterface::class);
        $database
            ->expects(self::once())
            ->method('query')
            ->with("INSERT IGNORE INTO system_module(code,name,enabled,sort_order) VALUES ('system.notifications','Oznámení',1,58)")
            ->willReturn(true);

        (new RegisterNotificationsModule($database))->up(
            (new \ReflectionClass(Schema::class))->newInstanceWithoutConstructor(),
        );
    }
}
