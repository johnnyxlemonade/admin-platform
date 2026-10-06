<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use InvalidArgumentException;

/**
 * Popisuje data prenosu identity pres OIDC
 */
final readonly class OidcProviderMetadata
{
    /**
     * Nastavuje data potrebna pro overeni identity
     * @param list<string> $codeChallengeMethodsSupported
     */
    public function __construct(
        private string $issuer,
        private string $authorizationEndpoint,
        private string $tokenEndpoint,
        private string $jwksUri,
        private array $codeChallengeMethodsSupported,
        private ?string $endSessionEndpoint = null,
    ) {
        if ($issuer === '' || !$this->isHttpsUri($authorizationEndpoint) || !$this->isHttpsUri($tokenEndpoint) || !$this->isHttpsUri($jwksUri) || ($endSessionEndpoint !== null && !$this->isHttpsUri($endSessionEndpoint))) {
            throw new InvalidArgumentException('OIDC provider metadata contains an invalid endpoint.');
        }
    }

    /**
     * Rozhoduje stav issuer
     */
    public function issuer(): string
    {
        return $this->issuer;
    }

    /**
     * Vraci nebo zpracovava hodnotu authorizationendpoint pro overeni identity
     */
    public function authorizationEndpoint(): string
    {
        return $this->authorizationEndpoint;
    }

    /**
     * Vraci nebo zpracovava hodnotu tokenendpoint pro overeni identity
     */
    public function tokenEndpoint(): string
    {
        return $this->tokenEndpoint;
    }

    /**
     * Vraci nebo zpracovava hodnotu jwksuri pro overeni identity
     */
    public function jwksUri(): string
    {
        return $this->jwksUri;
    }

    /**
     * Vraci nebo zpracovava hodnotu codechallengemethodssupported pro overeni identity
     * @return list<string>
     */
    public function codeChallengeMethodsSupported(): array
    {
        return $this->codeChallengeMethodsSupported;
    }

    /**
     * Vraci nebo zpracovava hodnotu endsessionendpoint pro overeni identity
     */
    public function endSessionEndpoint(): ?string
    {
        return $this->endSessionEndpoint;
    }

    /**
     * Rozhoduje stav ishttpsuri
     */
    private function isHttpsUri(string $uri): bool
    {
        $parts = parse_url($uri);

        return is_array($parts) && ($parts['scheme'] ?? null) === 'https' && isset($parts['host']);
    }
}
