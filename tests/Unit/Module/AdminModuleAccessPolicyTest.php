<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Module;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Module\AdminModuleAccessPolicy;
use Lemonade\Admin\System\Audit\AuditModuleDefinition;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

final class AdminModuleAccessPolicyTest extends TestCase
{
    public function testAuditIsNotBlockedByTheSuperadminOnlyModuleGate(): void
    {
        self::assertTrue($this->policy(true)->canAccess(new AuditModuleDefinition()));
    }

    public function testAuditAccessUsesItsViewPermissionInsteadOfTheSuperadminOnlyModuleGate(): void
    {
        self::assertTrue($this->policy(false)->canAccess(new AuditModuleDefinition()));
    }

    private function policy(bool $superAdmin): AdminModuleAccessPolicy
    {
        $connection = new class ($superAdmin) implements ConnectionInterface {
            public function __construct(private readonly bool $superAdmin) {}

            public function select(string $sql, array $bindings = []): array
            {
                return $this->superAdmin ? [['allowed' => 1]] : [];
            }

            public function cursor(string $sql, array $bindings = []): \Generator
            {
                yield from [];
            }

            public function statement(string $sql, array $bindings = []): int
            {
                return 0;
            }

            public function beginTransaction(): void {}

            public function commit(): void {}

            public function rollBack(): void {}

            public function inTransaction(): bool
            {
                return false;
            }

            public function transaction(callable $callback): mixed
            {
                return $callback($this);
            }

            public function lastInsertId(): int|string|null
            {
                return null;
            }

            public function affectedRows(): int
            {
                return 0;
            }

            public function reconnect(): void {}

            public function close(): void {}

            public function serverVersion(): string
            {
                return 'test';
            }

            public function escapeString(string $value): string
            {
                return $value;
            }
        };
        $principal = new class implements CurrentPrincipalProviderInterface {
            public function currentPrincipal(): AdminPrincipalInterface
            {
                return new LocalAdminPrincipal($this->currentUser());
            }

            public function currentUser(): AuthenticatedUser
            {
                return new AuthenticatedUser(1, 'root@example.test');
            }
        };
        $authorization = new AuthorizationService(
            $principal,
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
        );

        return new AdminModuleAccessPolicy($authorization, $principal);
    }
}
