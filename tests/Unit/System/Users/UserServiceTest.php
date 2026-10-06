<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDependencyResolver;
use Lemonade\Admin\Authorization\PermissionOverrideNormalizer;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\ExternalIdentityRepository;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Users\Editor\UserEditorInput;
use Lemonade\Admin\System\Users\Models\UserModel;
use Lemonade\Admin\System\Users\Models\UserPermissionOverrideModel;
use Lemonade\Admin\System\Users\Models\UserRoleModel;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    /**
     * Overuje, ze identicky editorovy submit nezmeni optimistic-lock verzi ani audit
     */
    public function testIdenticalEditorUpdateDoesNotWriteOrAudit(): void
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = $sql;
            if (str_contains($sql, 'system_user_role')) {
                return $this->databaseResult([]);
            }
            if (str_contains($sql, 'system_user_permission')) {
                return $this->databaseResult([]);
            }
            if (str_contains($sql, 'email') && str_contains($sql, 'id !=')) {
                return $this->databaseResult([]);
            }

            return $this->databaseResult([[
                'id' => 5,
                'username' => 'editor@example.test',
                'first_name' => 'Editor',
                'last_name' => 'User',
                'email' => 'editor@example.test',
                'phone' => null,
                'active' => 1,
                'version' => 7,
            ]]);
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
        $connection->method('select')->willReturn([]);
        $database = new Database($connection, $driver);
        $catalog = new PermissionCatalogRegistry();
        $dependencies = new PermissionDependencyResolver($catalog);
        $authorization = new AuthorizationService($principals, $database);
        $service = new UserService(
            new UserModel($driver),
            new UserRoleModel($driver),
            new UserPermissionOverrideModel($driver),
            (new \ReflectionClass(CurrentUserProvider::class))->newInstanceWithoutConstructor(),
            new AuthorizationDelegationPolicy($authorization, $catalog, $dependencies),
            $authorization,
            new PermissionOverrideNormalizer(),
            $dependencies,
            (new \ReflectionClass(TransactionalEventProcessor::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(EditorLockManager::class))->newInstanceWithoutConstructor(),
            new LocalActorGuard($principals),
            new ExternalIdentityRepository($database),
        );

        $service->updateEditor(5, UserEditorInput::fromValidated([
            'first_name' => 'Editor',
            'last_name' => 'User',
            'email' => 'editor@example.test',
            'phone' => '',
            'active' => '1',
            'version' => 7,
        ]));

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
