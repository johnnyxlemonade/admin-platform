<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;

/**
 * Zpracovava overenou identitu uzivatele
 */
final class LeagueOpenIdConnectClient implements OpenIdConnectClientInterface
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(
        private readonly OidcMetadataDiscovery $discovery,
        private readonly OidcIdTokenVerifier $idTokens,
        private readonly GuzzleClientInterface $http,
    ) {}

    /**
     * Nacita metadata OIDC poskytovatele
     */
    public function discover(OidcProviderConfiguration $configuration): OidcProviderMetadata
    {
        return $this->discovery->discover($configuration);
    }

    /**
     * Vraci nebo zpracovava hodnotu createauthorizationrequest pro overeni identity
     */
    public function createAuthorizationRequest(OidcProviderConfiguration $configuration, OidcProviderMetadata $metadata, string $state, string $nonce, string $codeVerifier): string
    {
        if (!$configuration->isEnabled()) {
            throw new OidcProtocolException('provider_disabled');
        }
        if (!in_array('S256', $metadata->codeChallengeMethodsSupported(), true)) {
            throw new OidcProtocolException('pkce_s256_unsupported');
        }

        return $metadata->authorizationEndpoint() . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $configuration->clientId(),
            'redirect_uri' => $configuration->redirectUri(),
            'scope' => implode(' ', $configuration->scopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Vraci nebo zpracovava hodnotu exchangeandverify pro overeni identity
     */
    public function exchangeAndVerify(OidcProviderConfiguration $configuration, OidcProviderMetadata $metadata, OidcAuthorizationCallback $callback, string $expectedNonce, string $codeVerifier): OidcVerifiedLogin
    {
        if (!$configuration->isEnabled()) {
            throw new OidcProtocolException('provider_disabled');
        }
        if ($callback->hasError()) {
            throw new OidcProtocolException('authorization_error');
        }
        $code = $callback->code();
        if ($code === null) {
            throw new OidcProtocolException('authorization_code_missing');
        }
        try {
            $provider = new GenericProvider(
                [
                    'clientId' => $configuration->clientId(),
                    'clientSecret' => $configuration->clientSecret(),
                    'redirectUri' => $configuration->redirectUri(),
                    'urlAuthorize' => $metadata->authorizationEndpoint(),
                    'urlAccessToken' => $metadata->tokenEndpoint(),
                    'urlResourceOwnerDetails' => '',
                ],
                ['httpClient' => $this->http],
            );
            $provider->setPkceCode($codeVerifier);
            $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
            $values = $token->getValues();
            $idToken = $values['id_token'] ?? null;
        } catch (\Throwable) {
            throw new OidcProtocolException('token_exchange_failed');
        }
        if (!is_string($idToken) || $idToken === '') {
            throw new OidcProtocolException('id_token_missing');
        }

        return new OidcVerifiedLogin(
            $this->idTokens->verify($idToken, $configuration, $metadata, $expectedNonce),
            $idToken,
        );
    }

    /**
     * Vraci nebo zpracovava hodnotu codechallenge pro overeni identity
     */
    public static function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
