<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth\Config;

use Lemonade\Admin\Auth\Config\AdminAuthConfigDefinition;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Overuje deklarativni host konfiguraci OIDC administrace
 */
final class AdminAuthConfigDefinitionTest extends TestCase
{
    /**
     * Overuje canonical Admin transport oddeleny od host session
     */
    public function testSessionConfigurationUsesCanonicalAdminIdentityAndMaximumLifetime(): void
    {
        $host = SessionConfigDefinition::create()
            ->lifetime(7200)
            ->cookieName('LEMONADE_SESSION')
            ->nativePath('sessions/host')
            ->filePath('sessions/host')
            ->redisPrefix('portal:')
            ->applyTo(SessionConfigDefinition::resolvedDefaults());

        $admin = AdminAuthConfigDefinition::create()->sessionConfiguration($host);

        self::assertSame('LEMONADE_ADMIN_SESSION', $admin->cookie()->name());
        self::assertSame(172800, $admin->lifetime());
        self::assertSame('sessions/admin', $admin->native()->path());
        self::assertSame('sessions/admin', $admin->file()->path());
        self::assertSame('portal:admin:', $admin->redis()->prefix());
        self::assertSame($host->driver(), $admin->driver());
        self::assertSame($host->cookie()->sameSite(), $admin->cookie()->sameSite());
        self::assertSame(7200, $host->lifetime());
        self::assertSame('LEMONADE_SESSION', $host->cookie()->name());
    }

    /**
     * Overuje volitelne zprisneni Admin session host aplikaci
     */
    public function testSessionConfigurationAcceptsAHostOverrideBelowTheMaximum(): void
    {
        $admin = AdminAuthConfigDefinition::fromArrayData([
            'session' => [
                'cookie_name' => 'CUSTOM_ADMIN_SESSION',
                'lifetime' => 3600,
                'storage_path' => 'sessions/custom-admin',
                'redis_prefix' => 'custom-admin:',
            ],
        ])->sessionConfiguration(SessionConfigDefinition::resolvedDefaults());

        self::assertSame('CUSTOM_ADMIN_SESSION', $admin->cookie()->name());
        self::assertSame(3600, $admin->lifetime());
        self::assertSame('sessions/custom-admin', $admin->native()->path());
        self::assertSame('sessions/custom-admin', $admin->file()->path());
        self::assertSame('custom-admin:', $admin->redis()->prefix());
    }

    /**
     * Overuje odmitnuti Admin session delsi nez absolutni limit identity
     */
    public function testSessionConfigurationRejectsLifetimeAboveTheMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('172800');

        AdminAuthConfigDefinition::fromArrayData([
            'session' => ['lifetime' => 1987200],
        ])->sessionConfiguration(SessionConfigDefinition::resolvedDefaults());
    }

    /**
     * Overuje, ze vypnuty throttling nevyzaduje limiter policy
     */
    public function testDisabledThrottlingDoesNotRequireBucketPolicies(): void
    {
        $configuration = AdminAuthConfigDefinition::fromArrayData([
            'throttling' => ['enabled' => false],
        ])->throttlingConfiguration();

        self::assertFalse($configuration->enabled());
    }

    /**
     * Overuje canonical policy bez hostem deklarovanych limiter cisel
     */
    public function testThrottlingDefaultsToEnabled(): void
    {
        $configuration = AdminAuthConfigDefinition::create()->throttlingConfiguration();

        self::assertTrue($configuration->enabled());
        self::assertSame(20, $configuration->loginIp()->attempts());
        self::assertSame(900, $configuration->loginIp()->windowSeconds());
        self::assertSame(5, $configuration->loginIdentity()->attempts());
        self::assertSame(900, $configuration->loginIdentity()->windowSeconds());
        self::assertSame(20, $configuration->mfaIp()->attempts());
        self::assertSame(900, $configuration->mfaIp()->windowSeconds());
        self::assertSame(5, $configuration->mfaIdentity()->attempts());
        self::assertSame(900, $configuration->mfaIdentity()->windowSeconds());
    }

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
    public function testConfigurationKeepsGroupListAndDefaultRoleValues(): void
    {
        $configuration = $this->definition([
            ...$this->enabledValues(),
            'base_url' => 'https://portal.example.test',
            'required_group' => 'administrators',
            'default_role' => 'editor',
            'scopes' => 'openid profile email profile',
            'allowed_id_token_algorithms' => 'RS256, ES256, RS256',
        ])->oidcConfiguration($this->routing());

        self::assertSame('administrators', $configuration->requiredGroup());
        self::assertSame('editor', $configuration->defaultRole());
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
