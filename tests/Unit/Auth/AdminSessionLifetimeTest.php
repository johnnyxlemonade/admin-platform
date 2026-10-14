<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use DateTimeImmutable;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;

final class AdminSessionLifetimeTest extends TestCase
{
    public function testLoginCreatesTheCanonicalSessionTimestamps(): void
    {
        $session = new AdminSessionLifetimeMemorySession();
        $authentication = $this->authentication($session, 1_700_000_000);

        $authentication->login(new AuthenticatedUser(42, 'admin@example.test'));

        self::assertSame(42, $session->get('admin.auth.user_id'));
        self::assertSame(1_700_000_000, $session->get('admin.auth.authenticated_at'));
        self::assertSame(1_700_000_000, $session->get('admin.auth.last_activity_at'));
    }

    public function testActivityBeforeTheIdleDeadlineKeepsTheSessionAuthenticated(): void
    {
        $session = $this->activeSession(authenticatedAt: 1_700_000_000, lastActivityAt: 1_700_000_000);

        self::assertTrue($this->authentication($session, 1_700_001_199)->hasActiveSession());
    }

    public function testIdleDeadlineLocksTheSessionWithoutRemovingItsIdentity(): void
    {
        $session = $this->activeSession(authenticatedAt: 1_700_000_000, lastActivityAt: 1_700_000_000);

        self::assertFalse($this->authentication($session, 1_700_001_200)->hasActiveSession());
        self::assertSame(42, $session->get('admin.auth.user_id'));
        self::assertSame(1_700_000_000, $session->get('admin.auth.last_activity_at'));
    }

    public function testHeartbeatDoesNotExtendALockedSession(): void
    {
        $session = $this->activeSession(authenticatedAt: 1_700_000_000, lastActivityAt: 1_700_000_000);

        self::assertFalse($this->authentication($session, 1_700_001_200)->heartbeat());
        self::assertSame(1_700_000_000, $session->get('admin.auth.last_activity_at'));
    }

    public function testUnlockMovesOnlyTheIdleActivityTimestamp(): void
    {
        $session = $this->activeSession(authenticatedAt: 1_700_000_000, lastActivityAt: 1_700_000_000);

        self::assertTrue($this->authentication($session, 1_700_001_200)->unlock());
        self::assertSame(1_700_000_000, $session->get('admin.auth.authenticated_at'));
        self::assertSame(1_700_001_200, $session->get('admin.auth.last_activity_at'));
    }

    public function testHeartbeatMovesOnlyTheIdleActivityTimestamp(): void
    {
        $session = $this->activeSession(authenticatedAt: 1_700_000_000, lastActivityAt: 1_700_000_100);

        self::assertTrue($this->authentication($session, 1_700_000_500)->heartbeat());
        self::assertSame(1_700_000_000, $session->get('admin.auth.authenticated_at'));
        self::assertSame(1_700_000_500, $session->get('admin.auth.last_activity_at'));
    }

    public function testAbsoluteDeadlineExpiresTheSessionDespiteRecentActivity(): void
    {
        $session = $this->activeSession(authenticatedAt: 1_700_000_000, lastActivityAt: 1_700_172_799);

        self::assertFalse($this->authentication($session, 1_700_172_800)->hasActiveSession());
        self::assertNull($session->get('admin.auth.user_id'));
    }

    private function activeSession(int $authenticatedAt, int $lastActivityAt): AdminSessionLifetimeMemorySession
    {
        $session = new AdminSessionLifetimeMemorySession();
        $session->set('admin.auth.user_id', 42);
        $session->set('admin.auth.authenticated_at', $authenticatedAt);
        $session->set('admin.auth.last_activity_at', $lastActivityAt);

        return $session;
    }

    private function authentication(SessionInterface $session, int $timestamp): AuthenticationService
    {
        $database = new Database(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(DatabaseDriverInterface::class),
        );
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@' . $timestamp));

        return new AuthenticationService(
            new LocalAuthenticationProvider($database),
            $session,
            $database,
            new CsrfTokenManager($session),
            $this->createMock(AuditLogWriterInterface::class),
            clock: $clock,
        );
    }
}

final class AdminSessionLifetimeMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}

    public function started(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function regenerate(bool $deleteOldSession = true): void {}
}
