<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventDispatcher;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\ExternalIdentityRepository;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Users\Exceptions\UserSafetyException;
use Lemonade\Admin\System\Users\Models\UserModel;
use Lemonade\Admin\System\Users\Services\SelfPasswordChangeService;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Verifies secure self-service password changes
 */
final class SelfPasswordChangeServiceTest extends TestCase
{
    /**
     * Verifies current-password authentication, persistence, and audit
     */
    public function testItVerifiesCurrentPasswordAndAuditsTheNewCredential(): void
    {
        $passwordHash = password_hash('current-password', PASSWORD_DEFAULT);
        $user = $this->user($passwordHash);
        $connection = $this->connection($user);
        $database = new Database($connection, self::createStub(DatabaseDriverInterface::class));
        $queries = [];
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::once())->method('record')->with(
            self::callback(static fn(DomainEvent $event): bool => $event->code() === 'system.users.password_changed' && $event->entityKey() === '7'),
            self::callback(static fn(AuditActor $actor): bool => $actor->userId() === 7),
        );
        $service = $this->service($database, $user, $audit, $queries);

        $service->change(7, 'current-password', 'new-secure-password');

        $passwordUpdate = array_values(array_filter($queries, static fn(array $query): bool => str_starts_with($query[0], 'UPDATE system_user')));
        self::assertCount(1, $passwordUpdate);
        self::assertTrue(password_verify('new-secure-password', (string) $passwordUpdate[0][1][0]));
    }

    /**
     * Verifies invalid current credentials cannot write a password
     */
    public function testItRejectsAnInvalidCurrentPasswordWithoutWriting(): void
    {
        $user = $this->user(password_hash('current-password', PASSWORD_DEFAULT));
        $database = new Database($this->connection($user), self::createStub(DatabaseDriverInterface::class));
        $queries = [];
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::never())->method('record');
        $service = $this->service($database, $user, $audit, $queries);

        try {
            $service->change(7, 'wrong-password', 'new-secure-password');
            self::fail('Invalid current password must be rejected.');
        } catch (UserSafetyException $exception) {
            self::assertSame('users.validation.current_password_invalid', $exception->translationKey());
        }

        self::assertSame([], array_filter($queries, static fn(array $query): bool => str_starts_with($query[0], 'UPDATE system_user')));
    }

    /**
     * Creates the service with database and identity test doubles
     *
     * @param array<string, mixed> $user
     * @param list<array{0:string,1:array<array-key, mixed>}> $queries
     */
    private function service(Database $database, array $user, AuditLogWriterInterface $audit, array &$queries): SelfPasswordChangeService
    {
        $driver = self::createStub(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use ($user, &$queries): DatabaseResultInterface {
            $queries[] = [$sql, $bindings === false ? [] : $bindings];

            return $this->databaseResult(str_starts_with($sql, 'SELECT') ? [$user] : []);
        });
        $currentUser = new AuthenticatedUser(7, 'profile@example.test');
        $principals = self::createStub(CurrentPrincipalProviderInterface::class);
        $principals->method('currentUser')->willReturn($currentUser);
        $principals->method('currentPrincipal')->willReturn(new LocalAdminPrincipal($currentUser));

        return new SelfPasswordChangeService(
            new UserModel($driver),
            new ExternalIdentityRepository($database),
            new LocalAuthenticationProvider($database),
            new LocalActorGuard($principals),
            new TransactionalEventProcessor($database, $audit, new DomainEventDispatcher(new NullLogger())),
        );
    }

    /**
     * Creates a transactional connection for one prepared user
     *
     * @param array<string, mixed> $user
     */
    private function connection(array $user): ConnectionInterface
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(static function (string $sql) use ($user): array {
            return str_contains($sql, 'system_user_identity') ? [] : [$user];
        });
        $connection->method('transaction')->willReturnCallback(static fn(callable $callback): mixed => $callback());

        return $connection;
    }

    /**
     * Returns a user row with the supplied password hash
     *
     * @return array<string, mixed>
     */
    private function user(string $passwordHash): array
    {
        return [
            'id' => 7,
            'email' => 'profile@example.test',
            'first_name' => 'Profile',
            'last_name' => 'User',
            'phone' => null,
            'active' => 1,
            'version' => 1,
            'last_login_at' => null,
            'updated_at' => '2026-11-27 09:00:00',
            'created_at' => '2026-11-24 09:00:00',
            'password_hash' => $passwordHash,
        ];
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
