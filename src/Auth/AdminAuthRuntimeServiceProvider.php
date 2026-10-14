<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Lemonade\Admin\Auth\Config\AdminAuthConfigDefinition;
use Lemonade\Admin\Auth\Config\AdminAuthenticationThrottleConfiguration;
use Lemonade\Admin\Auth\Mfa\AdminMfaConfiguration;
use Lemonade\Admin\Auth\Mfa\MfaSecretCipher;
use Lemonade\Admin\Auth\Oidc\Contract\OidcJwksProviderInterface;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Auth\Oidc\HttpOidcJwksProvider;
use Lemonade\Admin\Auth\Oidc\LeagueOpenIdConnectClient;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\RateLimit\RateLimiterInterface;
use Psr\Http\Client\ClientInterface as PsrHttpClientInterface;

/**
 * Registruje package-neutralni runtime pro lokalni a OIDC admin autentizaci
 *
 * AdminPackage jej zapojuje do bootstrapu po host providerech. Host prida jen
 * typed admin_auth konfiguraci; tento provider z ni nejdrive svaze jednu OIDC
 * konfiguraci a az pote registruje jeji runtime konzumenty.
 */
final class AdminAuthRuntimeServiceProvider implements ServiceProviderInterface
{
    /**
     * Registruje host konfiguraci, lokalni autentizaci a generic OIDC klienty
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(OidcProviderConfiguration::class, fn(ContainerInterface $container): OidcProviderConfiguration => $this->oidcConfiguration($container));
        $container->singleton(AdminAuthenticationThrottleConfiguration::class, fn(ContainerInterface $container): AdminAuthenticationThrottleConfiguration => $this->throttlingConfiguration($container));
        $container->scoped(AuthenticationThrottler::class, static function (ContainerInterface $container): AuthenticationThrottler {
            $configuration = $container->get(AdminAuthenticationThrottleConfiguration::class);

            return new AuthenticationThrottler(
                limiter: $configuration->enabled() ? $container->get(RateLimiterInterface::class) : null,
                configuration: $configuration,
                requests: $container->get(HttpRequestInspector::class),
                database: $configuration->enabled() ? $container->get(Database::class) : null,
            );
        });
        $container->transient(AdminMfaConfiguration::class, fn(ContainerInterface $container): AdminMfaConfiguration => $this->mfaConfiguration($container));
        $container->transient(
            MfaSecretCipher::class,
            static function (ContainerInterface $container): MfaSecretCipher {
                /** @var AdminMfaConfiguration $configuration */
                $configuration = $container->get(AdminMfaConfiguration::class);

                return $configuration->enabled()
                    ? MfaSecretCipher::fromKey($configuration->binaryEncryptionKey())
                    : MfaSecretCipher::disabled();
            },
        );
        $container->transient(GuzzleClientInterface::class, Client::class);
        $container->transient(PsrHttpClientInterface::class, Client::class);
        $container->singleton(OidcJwksProviderInterface::class, HttpOidcJwksProvider::class);
        $container->transient(OpenIdConnectClientInterface::class, LeagueOpenIdConnectClient::class);
    }

    /**
     * Vrati jedinou hostem deklarovanou nebo vypnutou vychozi OIDC konfiguraci
     */
    private function oidcConfiguration(ContainerInterface $container): OidcProviderConfiguration
    {
        return $this->configurationDefinition($container)->oidcConfiguration($container->get(AdminRoutingConfiguration::class));
    }

    /**
     * Vrati jedinou hostem deklarovanou local MFA konfiguraci
     */
    private function mfaConfiguration(ContainerInterface $container): AdminMfaConfiguration
    {
        return $this->configurationDefinition($container)->mfaConfiguration();
    }

    /**
     * Vrati jedinou hostem deklarovanou throttling konfiguraci lokalni autentizace
     */
    private function throttlingConfiguration(ContainerInterface $container): AdminAuthenticationThrottleConfiguration
    {
        return $this->configurationDefinition($container)->throttlingConfiguration();
    }

    /**
     * Vrati jedinou hostem deklarovanou nebo vychozi Admin Auth definici
     */
    private function configurationDefinition(ContainerInterface $container): AdminAuthConfigDefinition
    {
        $definitions = $container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
            AdminAuthConfigDefinition::moduleKey(),
            AdminAuthConfigDefinition::class,
        );
        if (count($definitions) > 1) {
            throw new \LogicException('Admin auth accepts only one configuration definition.');
        }

        return $definitions[0] ?? AdminAuthConfigDefinition::create();
    }
}
