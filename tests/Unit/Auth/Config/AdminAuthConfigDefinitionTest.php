<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth\Config;

use Lemonade\Admin\Auth\Config\AdminAuthConfigDefinition;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Overuje deklarativni host konfiguraci OIDC administrace
 */
final class AdminAuthConfigDefinitionTest extends TestCase
{
    /**
     * Overuje vypnutou konfiguraci bez bezpecnostnich udaju
     */
    public function testDisabledConfigurationBootsWithoutCredentials(): void
    {
        $configuration = AdminAuthConfigDefinition::create()->oidcConfiguration($this->routing());

        self::assertFalse($configuration->isEnabled());
        self::assertSame('oidc', $configuration->provider());
    }

    /**
     * Overuje defaultni callback a logout URL z host base URL
     */
    public function testEnabledConfigurationBuildsDefaultAdminUrls(): void
    {
        $configuration = $this->definition([
            ...$this->enabledValues(),
            'base_url' => 'https://portal.example.test/',
        ])->oidcConfiguration($this->routing());

        self::assertSame('https://portal.example.test/backoffice/auth/keycloak/callback', $configuration->redirectUri());
        self::assertSame('https://portal.example.test/backoffice/login', $configuration->postLogoutRedirectUri());
        self::assertSame(['openid', 'profile', 'email'], $configuration->scopes());
        self::assertSame(['RS256'], $configuration->allowedIdTokenAlgorithms());
    }

    /**
     * Overuje explicitni callback a logout URL bez odvozovani z base URL
     */
    public function testEnabledConfigurationKeepsExplicitUrls(): void
    {
        $configuration = $this->definition([
            ...$this->enabledValues(),
            'redirect_uri' => 'https://login.example.test/callback',
            'post_logout_redirect_uri' => 'https://portal.example.test/goodbye',
        ])->oidcConfiguration($this->routing());

        self::assertSame('https://login.example.test/callback', $configuration->redirectUri());
        self::assertSame('https://portal.example.test/goodbye', $configuration->postLogoutRedirectUri());
    }

    /**
     * Overuje zachovani group a listovych OIDC hodnot z host konfigurace
     */
    public function testConfigurationKeepsGroupAndListValues(): void
    {
        $configuration = $this->definition([
            ...$this->enabledValues(),
            'base_url' => 'https://portal.example.test',
            'required_group' => 'administrators',
            'scopes' => 'openid profile email profile',
            'allowed_id_token_algorithms' => 'RS256, ES256, RS256',
        ])->oidcConfiguration($this->routing());

        self::assertSame('administrators', $configuration->requiredGroup());
        self::assertSame(['openid', 'profile', 'email'], $configuration->scopes());
        self::assertSame(['RS256', 'ES256'], $configuration->allowedIdTokenAlgorithms());
    }

    /**
     * Overuje fail-fast pri chybejici base URL potrebne pro defaultni URI
     */
    public function testEnabledConfigurationRejectsMissingBaseUrlForDerivedUrls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('base_url');

        $this->definition($this->enabledValues())->oidcConfiguration($this->routing());
    }

    /**
     * Vytvori typed definici s OIDC hodnotami pro konkretni test
     *
     * @param array<string,mixed> $oidc
     */
    private function definition(array $oidc): AdminAuthConfigDefinition
    {
        return AdminAuthConfigDefinition::fromArrayData(['oidc' => $oidc]);
    }

    /**
     * Vrati minimalni bezpecnostni hodnoty zapnuteho OIDC providera
     *
     * @return array<string,mixed>
     */
    private function enabledValues(): array
    {
        return [
            'provider' => 'host-provider',
            'enabled' => true,
            'issuer' => 'https://issuer.example.test/realm',
            'client_id' => 'admin-client',
            'client_secret' => 'client-secret',
            'scopes' => ['openid', 'profile', 'email'],
            'required_group' => '',
            'allowed_id_token_algorithms' => ['RS256'],
            'redirect_uri' => '',
            'post_logout_redirect_uri' => '',
        ];
    }

    /**
     * Vrati nestandardni admin cestu pro kontrolu odvozenych URL
     */
    private function routing(): AdminRoutingConfiguration
    {
        return new AdminRoutingConfiguration('/backoffice');
    }
}
