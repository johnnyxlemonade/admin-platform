<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

/**
 * Overuje, zda muze OIDC ucet do administrace
 */
final class OidcAdmissionPolicy
{
    public function allows(OidcProviderConfiguration $configuration, VerifiedExternalIdentity $identity): bool
    {
        $requiredGroup = $configuration->requiredGroup();

        return $requiredGroup === null || in_array(trim($requiredGroup, '/'), $identity->groups(), true);
    }
}
