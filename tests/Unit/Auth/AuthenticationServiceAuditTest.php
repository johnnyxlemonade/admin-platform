<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Editor\Lock\EditorLockOwnerReleaserInterface;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceAuditTest extends TestCase
{
    public function testSuccessfulLocalLoginRecordsTheAuthenticatedUserAfterSessionEstablishment(): void
    {
        $session = new AuthenticationAuditMemorySession();
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::once())->method('record')->willReturnCallback(
            function (DomainEvent $event, AuditOperation $operation) use ($session): void {
                self::assertSame(42, $session->get('admin.auth.user_id'));
                self::assertSame('system.authentication.login', $event->code());
                self::assertSame('user', $event->entityType());
                self::assertSame('42', $event->entityKey());
                self::assertSame(['method' => 'local'], $event->payload());
                self::assertSame('auth.login', $operation->code());
                self::assertSame(42, $operation->actor()->userId());
            },
        );

        $this->authentication($session, $audit)->login(new AuthenticatedUser(42, 'admin@example.test'));
    }

    public function testLogoutRecordsLocalActorBeforeClearingTheSession(): void
    {
        $session = new AuthenticationAuditMemorySession();
        $session->set('admin.auth.user_id', 42);
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::once())->method('record')->willReturnCallback(
            function (DomainEvent $event, AuditOperation $operation) use ($session): void {
                self::assertSame(42, $session->get('admin.auth.user_id'));
                self::assertSame('system.authentication.logout', $event->code());
                self::assertSame('42', $event->entityKey());
                self::assertSame(['method' => 'local'], $event->payload());
                self::assertSame('auth.logout', $operation->code());
                self::assertSame(42, $operation->actor()->userId());
            },
        );

        $locks = $this->createMock(EditorLockOwnerReleaserInterface::class);
        $locks->expects(self::once())->method('releaseOwnedByUser')->with(42);

        $this->authentication($session, $audit, $locks)->logout();

        self::assertNull($session->get('admin.auth.user_id'));
    }

    public function testSuccessfulOidcLoginRecordsOnlyMethodAndProviderMetadata(): void
    {
        $session = new AuthenticationAuditMemorySession();
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::once())->method('record')->willReturnCallback(
            function (DomainEvent $event, AuditOperation $operation) use ($session): void {
                self::assertSame('user', $operation->actor()->type()->value);
                self::assertSame('system.authentication.login', $event->code());
                self::assertSame('user', $event->entityType());
                self::assertSame(['method' => 'oidc', 'provider' => 'provider-a'], $event->payload());
                self::assertSame('42', $event->entityKey());
                self::assertSame('auth.login', $operation->code());
                self::assertSame(42, $session->get('admin.auth.user_id'));
            },
        );

        $this->authentication($session, $audit)->loginOidc(42, 'provider-a', 'id-token-not-audit-metadata');
    }

    public function testOidcLogoutRecordsTheLocalActorBeforeClearingTheSession(): void
    {
        $session = new AuthenticationAuditMemorySession();
        $session->set('admin.auth.user_id', 42);
        $session->set('admin.auth.external_principal', [
            'kind' => 'external_oidc',
            'providerKey' => 'provider-a',
        ]);
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::once())->method('record')->willReturnCallback(
            function (DomainEvent $event, AuditOperation $operation) use ($session): void {
                self::assertNotNull($session->get('admin.auth.external_principal'));
                self::assertSame('user', $operation->actor()->type()->value);
                self::assertSame(42, $operation->actor()->userId());
                self::assertSame('system.authentication.logout', $event->code());
                self::assertSame(['method' => 'oidc', 'provider' => 'provider-a'], $event->payload());
                self::assertSame('auth.logout', $operation->code());
            },
        );

        $this->authentication($session, $audit)->logout();

        self::assertNull($session->get('admin.auth.external_principal'));
    }

    private function authentication(SessionInterface $session, AuditLogWriterInterface $audit, ?EditorLockOwnerReleaserInterface $locks = null): AuthenticationService
    {
        $database = new Database(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(DatabaseDriverInterface::class),
        );

        return new AuthenticationService(
            new LocalAuthenticationProvider($database),
            $session,
            $database,
            new CsrfTokenManager($session),
            $audit,
            $locks ?? $this->createMock(EditorLockOwnerReleaserInterface::class),
        );
    }
}

final class AuthenticationAuditMemorySession implements SessionInterface
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
