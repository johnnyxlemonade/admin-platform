<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use InvalidArgumentException;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Overuje invarianty generic OIDC konfigurace Admin package
 */
final class OidcProviderConfigurationTest extends TestCase
{
    /**
     * Overuje ze vypnuty provider nevyzaduje bezpecnostni udaje
     */
    public function testDisabledProviderDoesNotRequireCredentials(): void
    {
        $configuration = new OidcProviderConfiguration('provider-a', false, '', '', '', '', [], null, [], '');

        self::assertSame('provider-a', $configuration->provider());
        self::assertFalse($configuration->isEnabled());
    }

    /**
     * Overuje zachovani explicitnich URI a bezpecnostnich hodnot enabled providera
     */
    public function testEnabledProviderKeepsItsExplicitRedirectUriAndSecuritySettings(): void
    {
        $configuration = new OidcProviderConfiguration(
            'provider-a',
            true,
            'https://issuer.example.test/realm',
            'admin-client',
            'client-secret',
            'https://host.example.test/backoffice/auth/keycloak/callback',
            ['openid', 'profile', 'email'],
            'administrators',
            ['RS256'],
            'https://host.example.test/backoffice/login',
            'editor',
        );

        self::assertTrue($configuration->isEnabled());
        self::assertSame('https://host.example.test/backoffice/auth/keycloak/callback', $configuration->redirectUri());
        self::assertSame(['openid', 'profile', 'email'], $configuration->scopes());
        self::assertSame('administrators', $configuration->requiredGroup());
        self::assertSame(['RS256'], $configuration->allowedIdTokenAlgorithms());
        self::assertSame('https://host.example.test/backoffice/login', $configuration->postLogoutRedirectUri());
        self::assertSame('editor', $configuration->defaultRole());
    }

    /**
     * Odmita enabled provider bez povinneho OpenID scope
     */
    public function testEnabledProviderRejectsMissingOpenIdScope(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OidcProviderConfiguration(
            'provider-a',
            true,
            'https://issuer.example.test/realm',
            'admin-client',
            'client-secret',
            'https://host.example.test/backoffice/auth/keycloak/callback',
            ['profile', 'email'],
            null,
            ['RS256'],
            'https://host.example.test/backoffice/login',
        );
    }

    /**
     * Odmita relativni callback URI enabled providera
     */
    public function testEnabledProviderRejectsRelativeRedirectUri(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OidcProviderConfiguration(
            'provider-a',
            true,
            'https://issuer.example.test/realm',
            'admin-client',
            'client-secret',
            '/backoffice/auth/keycloak/callback',
            ['openid'],
            null,
            ['RS256'],
            'https://host.example.test/backoffice/login',
        );
    }

    /**
     * Odmita relativni post-logout URI enabled providera
     */
    public function testEnabledProviderRejectsRelativePostLogoutRedirectUri(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OidcProviderConfiguration(
            'provider-a',
            true,
            'https://issuer.example.test/realm',
            'admin-client',
            'client-secret',
            'https://host.example.test/backoffice/auth/keycloak/callback',
            ['openid'],
            null,
            ['RS256'],
            '/backoffice/login',
        );
    }
}
