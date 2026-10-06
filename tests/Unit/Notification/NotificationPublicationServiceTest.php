<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Notification;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\AuthorizationTargetPolicy;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDependencyResolver;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\NotificationPublicationService;
use Lemonade\Admin\Notification\NotificationType;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;

final class NotificationPublicationServiceTest extends TestCase
{
    /**
     * Overuje, ze stejne metadata a canonical audience neprovedou update ani reset zobrazeni
     */
    public function testIdenticalUpdateDoesNotWriteResetDisplayOrAudit(): void
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = $sql;
            if (str_contains($sql, 'FROM system_role')) {
                return $this->databaseResult([['id' => 3, 'code' => 'editor', 'name' => 'Editor']]);
            }
            if (str_contains($sql, 'FROM admin_notification_audience_role')) {
                return $this->databaseResult([['role_id' => 3]]);
            }
            if (str_contains($sql, 'FROM admin_notification_audience_user')) {
                return $this->databaseResult([]);
            }

            return $this->databaseResult([['id' => 11, 'type' => 'info', 'title' => 'Údržba', 'message' => 'Dnes večer', 'active' => 1, 'deleted_at' => null]]);
        });
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturn([['exists' => 1]]);
        $authorization = new AuthorizationService($this->createMock(CurrentPrincipalProviderInterface::class), new Database($connection, $driver));
        $catalog = new PermissionCatalogRegistry();
        $service = new NotificationPublicationService(
            new NotificationModel($driver),
            $authorization,
            new AuthorizationDelegationPolicy($authorization, $catalog, new PermissionDependencyResolver($catalog)),
            new AuthorizationTargetPolicy($authorization),
            (new \ReflectionClass(TransactionalEventProcessor::class))->newInstanceWithoutConstructor(),
        );

        $service->update(new AuthenticatedUser(7, 'admin@example.test'), 11, NotificationType::Info, 'Údržba', 'Dnes večer', [3, 3], []);

        self::assertNotEmpty($queries);
        self::assertFalse((bool) array_filter($queries, static fn(string $sql): bool => preg_match('/\\b(UPDATE|INSERT|DELETE)\\b/', $sql) === 1));
    }

    /**
     * Vytvari vysledek databazoveho dotazu s predanymi radky
     *
     * @param list<array<string, mixed>> $rows
     */
    private function databaseResult(array $rows): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn($rows);

        return $result;
    }
}
