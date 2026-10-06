<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;

/**
 * Ridi odhlaseni pres OIDC poskytovatele
 */
final class OidcLogoutService
{
    public function __construct(
        private readonly OidcProviderConfiguration $configuration,
        private readonly OpenIdConnectClientInterface $client,
    ) {}

    public function providerLogoutUrl(?string $idTokenHint): ?string
    {
        if (!$this->configuration->isEnabled()) {
            return null;
        }

        try {
            $endpoint = $this->client->discover($this->configuration)->endSessionEndpoint();
        } catch (\Throwable) {
            return null;
        }
        if ($endpoint === null) {
            return null;
        }

        $parameters = [
            'client_id' => $this->configuration->clientId(),
            'post_logout_redirect_uri' => $this->configuration->postLogoutRedirectUri(),
        ];
        if ($idTokenHint !== null && $idTokenHint !== '') {
            $parameters['id_token_hint'] = $idTokenHint;
        }

        return $endpoint . (str_contains($endpoint, '?') ? '&' : '?') . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
