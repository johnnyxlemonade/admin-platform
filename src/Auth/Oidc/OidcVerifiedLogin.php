<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use InvalidArgumentException;

/**
 * Zpracovava overenou identitu uzivatele
 */
final readonly class OidcVerifiedLogin
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(
        private VerifiedExternalIdentity $identity,
        private string $idToken,
    ) {
        if ($idToken === '') {
            throw new InvalidArgumentException('A verified OIDC login requires an ID token.');
        }
    }

    /**
     * Vraci nebo zpracovava hodnotu identity pro overeni identity
     */
    public function identity(): VerifiedExternalIdentity
    {
        return $this->identity;
    }

    /**
     * Vraci nebo zpracovava hodnotu idtoken pro overeni identity
     */
    public function idToken(): string
    {
        return $this->idToken;
    }
}
