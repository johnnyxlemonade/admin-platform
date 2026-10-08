<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc\Contract;

use Firebase\JWT\Key;
use Lemonade\Admin\Auth\Oidc\OidcProviderMetadata;

/**
 * Urcuje kontrakt pro overeni nebo poskytnuti identity
 */
interface OidcJwksProviderInterface
{
    /**
     * Vraci klice JWKS indexovane podle kid
     *
     * @return array<string, Key>
     */
    public function keySet(OidcProviderMetadata $metadata, bool $refresh = false): array;
}
