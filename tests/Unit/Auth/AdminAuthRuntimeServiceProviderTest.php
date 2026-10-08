<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Lemonade\Admin\Auth\AdminAuthRuntimeServiceProvider;
use Lemonade\Admin\Auth\Config\AdminAuthConfigDefinition;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Auth\Oidc\OidcIdTokenVerifier;
use Lemonade\Admin\Auth\Oidc\OidcMetadataDiscovery;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
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

        self::assertTrue($container->isBound(LocalAuthenticationProvider::class));
        self::assertTrue($container->isBound(GuzzleClientInterface::class));
        self::assertTrue($container->isBound(PsrHttpClientInterface::class));
        self::assertTrue($container->isBound(OidcMetadataDiscovery::class));
        self::assertTrue($container->isBound(OidcJwksProviderInterface::class));
        self::assertTrue($container->isBound(OidcIdTokenVerifier::class));
        self::assertTrue($container->isBound(OpenIdConnectClientInterface::class));
        self::assertTrue($container->isBound(OidcProviderConfiguration::class));
        self::assertSame('host-provider', $container->get(OidcProviderConfiguration::class)->provider());
        self::assertFalse($container->get(OidcProviderConfiguration::class)->isEnabled());
    }
}
