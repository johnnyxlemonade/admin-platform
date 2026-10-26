<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Lemonade\Admin\Auth\AdminAuthRuntimeServiceProvider;
use Lemonade\Admin\Auth\AuthenticationThrottler;
use Lemonade\Admin\Auth\Config\AdminAuthConfigDefinition;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Container\ServiceLifetime;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as PsrHttpClientInterface;

final class AdminAuthRuntimeServiceProviderTest extends TestCase
{
    public function testItRegistersGenericAuthRuntimeWithTypedHostConfiguration(): void
    {
        $container = new Container();
        $configurations = new ConfigDefinitionRegistry();
        $configurations->addDefinition(AdminAuthConfigDefinition::fromArrayData([
            'oidc' => [
                'provider' => 'host-provider',
                'enabled' => false,
            ],
        ]));
        $container->singleton(ConfigDefinitionRegistry::class, $configurations);
        $container->singleton(AdminRoutingConfiguration::class, new AdminRoutingConfiguration('/admin'));

        (new AdminAuthRuntimeServiceProvider())->register($container);

        self::assertFalse($container->isBound(LocalAuthenticationProvider::class));
        self::assertTrue($container->isBound(GuzzleClientInterface::class));
        self::assertTrue($container->isBound(PsrHttpClientInterface::class));
        self::assertTrue($container->isBound(OidcJwksProviderInterface::class));
        self::assertTrue($container->isBound(OpenIdConnectClientInterface::class));
        self::assertTrue($container->isBound(OidcProviderConfiguration::class));
        self::assertTrue($container->isBound(AdminAuthConfigDefinition::class));
        self::assertSame(ServiceLifetime::Scoped, $container->compile()->definition(AuthenticationThrottler::class)?->lifetime);
        self::assertSame('host-provider', $container->get(OidcProviderConfiguration::class)->provider());
        self::assertFalse($container->get(OidcProviderConfiguration::class)->isEnabled());
    }

    /**
     * Overuje, ze vypnuty throttling nevytvari limiter ani databazovou zavislost
     */
    public function testDisabledThrottlingResolvesWithoutRateLimiterOrDatabase(): void
    {
        $container = new Container();
        $configurations = new ConfigDefinitionRegistry();
        $configurations->addDefinition(AdminAuthConfigDefinition::fromArrayData([
            'throttling' => ['enabled' => false],
        ]));
        $container->singleton(ConfigDefinitionRegistry::class, $configurations);

        (new AdminAuthRuntimeServiceProvider())->register($container);

        $scope = $container->beginScope(ScopeKind::Request);
        $scope->bindScopedInstance(HttpRequestInspector::class, new HttpRequestInspector());

        try {
            self::assertInstanceOf(AuthenticationThrottler::class, $scope->get(AuthenticationThrottler::class));
        } finally {
            $scope->close();
        }
    }
}
