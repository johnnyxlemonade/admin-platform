<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc\Contract;

use Lemonade\Admin\Auth\Oidc\OidcAuthorizationCallback;
use Lemonade\Admin\Auth\Oidc\OidcProviderConfiguration;
use Lemonade\Admin\Auth\Oidc\OidcProviderMetadata;
use Lemonade\Admin\Auth\Oidc\OidcVerifiedLogin;

/**
 * Urcuje kontrakt pro overeni nebo poskytnuti identity
 */
interface OpenIdConnectClientInterface
{
    /**
     * Nacita metadata OIDC poskytovatele
     */
    public function discover(OidcProviderConfiguration $configuration): OidcProviderMetadata;

    /**
     * Vraci nebo zpracovava hodnotu createauthorizationrequest pro overeni identity
     */
    public function createAuthorizationRequest(
        OidcProviderConfiguration $configuration,
        OidcProviderMetadata $metadata,
        string $state,
        string $nonce,
        string $codeVerifier,
    ): string;

    /**
     * Vraci nebo zpracovava hodnotu exchangeandverify pro overeni identity
     */
    public function exchangeAndVerify(
        OidcProviderConfiguration $configuration,
        OidcProviderMetadata $metadata,
        OidcAuthorizationCallback $callback,
        string $expectedNonce,
        string $codeVerifier,
    ): OidcVerifiedLogin;
}
