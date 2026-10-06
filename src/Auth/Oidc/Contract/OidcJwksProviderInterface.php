<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc\Contract;

use Jose\Component\Core\JWKSet;
use Lemonade\Admin\Auth\Oidc\OidcProviderMetadata;

/**
 * Urcuje kontrakt pro overeni nebo poskytnuti identity
 */
interface OidcJwksProviderInterface
{
    /**
     * Vraci nebo zpracovava hodnotu keyset pro overeni identity
     */
    public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): JWKSet;
}
