<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Auth\Oidc\HttpOidcJwksProvider;
use Lemonade\Admin\Auth\Oidc\LeagueOpenIdConnectClient;
use Lemonade\Admin\Auth\Oidc\OidcIdTokenVerifier;
use Lemonade\Admin\Auth\Oidc\OidcMetadataDiscovery;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Psr\Http\Client\ClientInterface as PsrHttpClientInterface;

/**
 * Registruje package-neutralni runtime pro lokalni a OIDC admin autentizaci
 */
final class AdminAuthRuntimeServiceProvider implements ServiceProviderInterface
{
    /**
     * Registruje lokalni autentizaci a generic OIDC klienty bez konfigurace hosta
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(LocalAuthenticationProvider::class, LocalAuthenticationProvider::class);
        $container->singleton(Client::class, Client::class);
        $container->singleton(GuzzleClientInterface::class, static fn(ContainerInterface $container): Client => $container->get(Client::class));
        $container->singleton(PsrHttpClientInterface::class, static fn(ContainerInterface $container): Client => $container->get(Client::class));
        $container->singleton(OidcMetadataDiscovery::class, OidcMetadataDiscovery::class);
        $container->singleton(HttpOidcJwksProvider::class, HttpOidcJwksProvider::class);
        $container->singleton(OidcJwksProviderInterface::class, HttpOidcJwksProvider::class);
        $container->singleton(OidcIdTokenVerifier::class, OidcIdTokenVerifier::class);
        $container->singleton(LeagueOpenIdConnectClient::class, LeagueOpenIdConnectClient::class);
        $container->singleton(OpenIdConnectClientInterface::class, LeagueOpenIdConnectClient::class);
    }
}
