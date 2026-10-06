<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;

final class OidcLocalUserSessionTest extends TestCase
{
    public function testOidcSessionKeepsLocalUserIdAndLogoutMetadata(): void
    {
        $session = $this->session();
        $authentication = $this->authentication($session);
        $authentication->loginOidc(42, 'provider-a', 'raw-id-token');

        self::assertSame(42, $authentication->sessionUserId());
        self::assertSame(
            ['kind', 'providerKey'],
            array_keys($authentication->oidcLogoutContext() ?? []),
        );
        self::assertSame('raw-id-token', $authentication->oidcLogoutIdToken());
        self::assertNull($session->get('admin.auth.external_principal.id_token'));
        self::assertNull($session->get('admin.auth.access_token'));
        self::assertNull($session->get('admin.auth.refresh_token'));
    }

    public function testOidcLogoutMetadataWithoutLocalUserIsAnonymous(): void
    {
        $session = $this->session();
        $session->set('admin.auth.external_principal', ['kind' => 'external_oidc', 'providerKey' => 'provider-a']);

        self::assertNull($this->authentication($session)->sessionUserId());
    }

    public function testOidcSessionTracksLocalUserChanges(): void
    {
        $session = $this->session();
        $authentication = $this->authentication($session);
        $authentication->loginOidc(41, 'provider-a', 'first-token');
        self::assertSame(41, $authentication->sessionUserId());
        $authentication->loginOidc(42, 'provider-a', 'second-token');
        self::assertSame(42, $authentication->sessionUserId());
    }

    public function testCurrentLocalUserIsReadOnceUntilExplicitInvalidation(): void
    {
        $session = $this->session();
        $session->set('admin.auth.user_id', 42);
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::exactly(2))->method('select')->willReturnOnConsecutiveCalls(
            [['id' => 42, 'email' => 'before@example.test']],
            [['id' => 42, 'email' => 'after@example.test']],
        );
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $provider = new CurrentUserProvider(
            new AuthenticationService(
                new LocalAuthenticationProvider($database),
                $session,
                $database,
                new CsrfTokenManager($session),
                $this->createMock(AuditLogWriterInterface::class),
            ),
            $database,
        );

        $first = $provider->currentUser();
        self::assertSame($first, $provider->currentUser());
        self::assertSame('before@example.test', $first?->email());

        $provider->invalidate();

        self::assertSame('after@example.test', $provider->currentUser()?->email());
    }

    public function testSeparateProviderInstancesDoNotShareCurrentUserCache(): void
    {
        $session = $this->session();
        $session->set('admin.auth.user_id', 42);
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::exactly(2))->method('select')->willReturn([
            ['id' => 42, 'email' => 'admin@example.test'],
        ]);
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $authentication = new AuthenticationService(
            new LocalAuthenticationProvider($database),
            $session,
            $database,
            new CsrfTokenManager($session),
            $this->createMock(AuditLogWriterInterface::class),
        );

        self::assertNotNull((new CurrentUserProvider($authentication, $database))->currentUser());
        self::assertNotNull((new CurrentUserProvider($authentication, $database))->currentUser());
    }

    public function testLogoutClearsAnOidcSession(): void
    {
        $session = $this->session();
        $authentication = $this->authentication($session);
        $authentication->loginOidc(42, 'provider-a', 'raw-id-token');

        $authentication->logout();

        self::assertNull($authentication->oidcLogoutContext());
        self::assertNull($authentication->sessionUserId());
        self::assertNull($authentication->oidcLogoutIdToken());
    }

    public function testLogoutClearsAnExistingLocalSessionWithoutChangingItsContract(): void
    {
        $session = $this->session();
        $session->set('admin.auth.user_id', 42);
        $authentication = $this->authentication($session);

        $authentication->logout();

        self::assertNull($authentication->sessionUserId());
        self::assertNull($authentication->oidcLogoutContext());
        self::assertNull($authentication->oidcLogoutIdToken());
    }

    public function testLocalLoginRemovesAnExistingOidcLogoutToken(): void
    {
        $session = $this->session();
        $authentication = $this->authentication($session);
        $authentication->loginOidc(41, 'provider-a', 'raw-id-token');

        $authentication->login(new AuthenticatedUser(42, 'local@example.test'));

        self::assertSame(42, $authentication->sessionUserId());
        self::assertNull($authentication->oidcLogoutContext());
        self::assertNull($authentication->oidcLogoutIdToken());
    }

    private function authentication(SessionInterface $session): AuthenticationService
    {
        $database = $this->database();

        return new AuthenticationService(
            new LocalAuthenticationProvider($database),
            $session,
            $database,
            new CsrfTokenManager($session),
            $this->createMock(AuditLogWriterInterface::class),
        );
    }

    private function database(): Database
    {
        return new Database(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(DatabaseDriverInterface::class),
        );
    }

    private function session(): SessionInterface
    {
        return new class implements SessionInterface {
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
        };
    }
}
