<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Lemonade\Admin\Auth\AdminAuthRuntimeServiceProvider;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Auth\Oidc\OidcIdTokenVerifier;
use Lemonade\Admin\Auth\Oidc\OidcMetadataDiscovery;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as PsrHttpClientInterface;

final class AdminAuthRuntimeServiceProviderTest extends TestCase
{
    public function testItRegistersGenericAuthRuntimeWithoutBindingHostConfiguration(): void
    {
        $container = new Container();

        (new AdminAuthRuntimeServiceProvider())->register($container);

        self::assertTrue($container->isBound(LocalAuthenticationProvider::class));
        self::assertTrue($container->isBound(GuzzleClientInterface::class));
        self::assertTrue($container->isBound(PsrHttpClientInterface::class));
        self::assertTrue($container->isBound(OidcMetadataDiscovery::class));
        self::assertTrue($container->isBound(OidcJwksProviderInterface::class));
        self::assertTrue($container->isBound(OidcIdTokenVerifier::class));
        self::assertTrue($container->isBound(OpenIdConnectClientInterface::class));
        self::assertFalse($container->isBound(OidcProviderConfiguration::class));
    }
}
