<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Auth\Oidc\OidcLogoutService;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Auth\Oidc\OidcProviderMetadata;
use PHPUnit\Framework\TestCase;

final class OidcLogoutServiceTest extends TestCase
{
    public function testItBuildsRpInitiatedLogoutUrlFromDiscoveredEndpoint(): void
    {
        $client = $this->createMock(OpenIdConnectClientInterface::class);
        $client->expects(self::once())->method('discover')->willReturn($this->metadata('https://issuer.example.test/logout'));

        $url = (new OidcLogoutService($this->configuration(), $client))->providerLogoutUrl('raw.id-token_hint');

        self::assertSame(
            'https://issuer.example.test/logout?client_id=lemonade-admin&post_logout_redirect_uri=https%3A%2F%2Fhost.example.test%2Fadmin%2Flogin&id_token_hint=raw.id-token_hint',
            $url,
        );
        self::assertStringContainsString('id_token_hint=raw.id-token_hint', $url);
    }

    public function testItFallsBackToLocalLogoutWhenProviderDoesNotAdvertiseAnEndSessionEndpoint(): void
    {
        $client = $this->createMock(OpenIdConnectClientInterface::class);
        $client->method('discover')->willReturn($this->metadata(null));

        self::assertNull((new OidcLogoutService($this->configuration(), $client))->providerLogoutUrl('raw-id-token'));
    }

    public function testItBuildsTheConfiguredFallbackLogoutUrlWithoutAnIdTokenHint(): void
    {
        $client = $this->createMock(OpenIdConnectClientInterface::class);
        $client->expects(self::once())->method('discover')->willReturn($this->metadata('https://issuer.example.test/logout'));

        $url = (new OidcLogoutService($this->configuration(), $client))->providerLogoutUrl(null);

        self::assertSame(
            'https://issuer.example.test/logout?client_id=lemonade-admin&post_logout_redirect_uri=https%3A%2F%2Fhost.example.test%2Fadmin%2Flogin',
            $url,
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

    private function metadata(?string $endSessionEndpoint): OidcProviderMetadata
    {
        return new OidcProviderMetadata(
            'https://issuer.example.test',
            'https://issuer.example.test/authorize',
            'https://issuer.example.test/token',
            'https://issuer.example.test/certs',
            ['S256'],
            $endSessionEndpoint,
        );
    }
}
