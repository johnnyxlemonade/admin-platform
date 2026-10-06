<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Auth\Oidc\OidcAdmissionPolicy;
use Lemonade\Admin\Auth\Oidc\OidcAuthenticationService;
use Lemonade\Admin\Auth\Oidc\OidcAuthorizationCallback;
use Lemonade\Admin\Auth\Oidc\OidcAuthorizationTransactionStore;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Auth\Oidc\OidcProviderMetadata;
use Lemonade\Admin\Auth\Oidc\OidcShadowUserProvisioner;
use Lemonade\Admin\Auth\Oidc\OidcVerifiedLogin;
use Lemonade\Admin\Auth\Oidc\VerifiedExternalIdentity;
use Lemonade\Admin\Identity\ExternalIdentityRepository;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;

final class OidcAuthenticationServiceTest extends TestCase
{
    public function testSuccessfulExternalLoginStoresOnlyTheIdTokenLogoutMetadataInTheServerSession(): void
    {
        $session = $this->session();
        $configuration = $this->configuration();
        $transactions = new OidcAuthorizationTransactionStore($session);
        $transaction = $transactions->begin('provider-a');
        $client = $this->createMock(OpenIdConnectClientInterface::class);
        $metadata = new OidcProviderMetadata(
            'https://issuer.example.test',
            'https://issuer.example.test/authorize',
            'https://issuer.example.test/token',
            'https://issuer.example.test/certs',
            ['S256'],
            'https://issuer.example.test/logout',
        );
        $client->method('discover')->willReturn($metadata);
        $client->expects(self::once())->method('exchangeAndVerify')->willReturn(new OidcVerifiedLogin(
            new VerifiedExternalIdentity('provider-a', 'https://issuer.example.test', 'subject-123', 'editor@example.test'),
            'raw-id-token',
        ));

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnOnConsecutiveCalls(
            [[
                'user_id' => 42,
                'provider_key' => 'provider-a',
                'issuer' => 'https://issuer.example.test',
                'subject' => 'subject-123',
            ]],
            [['active' => 1, 'deleted_at' => null]],
            [],
        );
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $authentication = $this->authentication($session, $database);
        $provisioner = new OidcShadowUserProvisioner(new ExternalIdentityRepository($database));
        $service = new OidcAuthenticationService(
            $configuration,
            $client,
            $transactions,
            new OidcAdmissionPolicy(),
            $provisioner,
            $authentication,
            new AdminRoutingConfiguration('/admin'),
        );

        self::assertSame('/admin', $service->complete(OidcAuthorizationCallback::fromQuery([
            'code' => 'authorization-code',
            'state' => $transaction->state(),
        ])));
        self::assertSame('raw-id-token', $authentication->oidcLogoutIdToken());
        self::assertSame(42, $authentication->sessionUserId());
        self::assertSame(
            ['kind', 'providerKey'],
            array_keys($authentication->oidcLogoutContext() ?? []),
        );
        self::assertNull($session->get('admin.auth.access_token'));
        self::assertNull($session->get('admin.auth.refresh_token'));
        self::assertSame([], $session->get('admin.auth.oidc.transactions'));
    }

    private function authentication(SessionInterface $session, Database $database): AuthenticationService
    {
        return new AuthenticationService(
            new LocalAuthenticationProvider($database),
            $session,
            $database,
            new CsrfTokenManager($session),
            $this->createMock(AuditLogWriterInterface::class),
        );
    }

    private function configuration(): OidcProviderConfiguration
    {
        return new OidcProviderConfiguration(
            'provider-a',
            true,
            'https://issuer.example.test',
            'lemonade-admin',
            'client-secret',
            'https://host.example.test/admin/auth/keycloak/callback',
            ['openid'],
            null,
            ['RS256'],
            'https://host.example.test/admin/login',
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
