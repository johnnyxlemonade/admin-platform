<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use InvalidArgumentException;

/**
 * Nese overenou konfiguraci jednoho OIDC poskytovatele
 */
final readonly class OidcProviderConfiguration
{
    /**
     * Nastavuje runtime hodnoty OIDC poskytovatele
     *
     * @param list<string> $scopes
     * @param list<string> $allowedIdTokenAlgorithms
     */
    public function __construct(
        private string $provider,
        private bool $enabled,
        private string $issuer,
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private array $scopes,
        private ?string $requiredGroup,
        private array $allowedIdTokenAlgorithms,
        private string $postLogoutRedirectUri,
    ) {
        if (trim($provider) === '') {
            throw new InvalidArgumentException('OIDC provider identifier must not be empty.');
        }
        if (!$enabled) {
            return;
        }
        if (!str_starts_with($issuer, 'https://') || $clientId === '' || $clientSecret === '' || !$this->isAbsoluteHttpUri($redirectUri) || !$this->isAbsoluteHttpUri($postLogoutRedirectUri)) {
            throw new InvalidArgumentException('An enabled OIDC provider requires issuer, client credentials, and absolute redirect URIs.');
        }
        if (!in_array('openid', $scopes, true) || $allowedIdTokenAlgorithms === []) {
            throw new InvalidArgumentException('An enabled OIDC provider requires the openid scope and at least one allowed ID token algorithm.');
        }
    }

    /**
     * Vrati technicky kod OIDC poskytovatele
     */
    public function provider(): string
    {
        return $this->provider;
    }

    /**
     * Rozhodne, zda je OIDC poskytovatel zapnuty
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Vrati issuer pouzity pro validaci tokenu
     */
    public function issuer(): string
    {
        return $this->issuer;
    }

    /**
     * Vrati client identifier OIDC aplikace
     */
    public function clientId(): string
    {
        return $this->clientId;
    }

    /**
     * Vrati client secret OIDC aplikace
     */
    public function clientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * Vrati absolutni callback URL
     */
    public function redirectUri(): string
    {
        return $this->redirectUri;
    }

    /**
     * Vrati scopes pozadovane pri OIDC autorizaci
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Vrati nepovinnou skupinu vyzadovanou pro pristup
     */
    public function requiredGroup(): ?string
    {
        return $this->requiredGroup;
    }

    /**
     * Vrati algoritmy povolene pro ID token
     *
     * @return list<string>
     */
    public function allowedIdTokenAlgorithms(): array
    {
        return $this->allowedIdTokenAlgorithms;
    }

    /**
     * Vrati absolutni cil po OIDC odhlaseni
     */
    public function postLogoutRedirectUri(): string
    {
        return $this->postLogoutRedirectUri;
    }

    /**
     * Overi absolutni HTTP nebo HTTPS URL
     */
    private function isAbsoluteHttpUri(string $uri): bool
    {
        $parts = parse_url($uri);

        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array($parts['scheme'], ['http', 'https'], true);
    }
}
