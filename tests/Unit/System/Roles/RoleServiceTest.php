<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Roles;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDependencyResolver;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Roles\Models\RoleModel;
use Lemonade\Admin\System\Roles\Services\RoleService;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;

final class RoleServiceTest extends TestCase
{
    /**
     * Overuje, ze shodna metadata a permission set roli neotevrou zapis ani audit
     */
    public function testIdenticalUpdateDoesNotWriteOrAudit(): void
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = $sql;
            if (str_contains($sql, 'name') && str_contains($sql, 'NOT IN')) {
                return $this->databaseResult([]);
            }

            return $this->databaseResult([['id' => 4, 'code' => 'editor', 'name' => 'Editor', 'description' => 'Publikuje obsah', 'is_system' => 0, 'is_super_admin' => 0]]);
        });
        $actor = new AuthenticatedUser(7, 'admin@example.test');
        $principals = new class ($actor) implements CurrentPrincipalProviderInterface {
            /**
             * Nastavuje lokalniho aktora testovane mutace
             */
            public function __construct(private AuthenticatedUser $actor) {}

            public function currentPrincipal(): AdminPrincipalInterface
            {
                return new LocalAdminPrincipal($this->actor);
            }

            public function currentUser(): AuthenticatedUser
            {
                return $this->actor;
            }
        };
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturn([['exists' => 1]]);
        $authorization = new AuthorizationService($principals, new Database($connection, $driver));
        $catalog = new PermissionCatalogRegistry();
        $service = new RoleService(
            new RoleModel($driver),
            $authorization,
            new AuthorizationDelegationPolicy($authorization, $catalog, new PermissionDependencyResolver($catalog)),
            $principals,
            $catalog,
            new PermissionDependencyResolver($catalog),
            (new \ReflectionClass(TransactionalEventProcessor::class))->newInstanceWithoutConstructor(),
            new LocalActorGuard($principals),
        );

        $service->update(4, 'Editor', 'Publikuje obsah', []);

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
