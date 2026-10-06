<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Identity\ActorIdentity;

/**
 * Zpracovava overenou identitu uzivatele
 */
final readonly class LocalAdminPrincipal implements AdminPrincipalInterface
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(private AuthenticatedUser $user) {}

    /**
     * Vraci overeneho uzivatele
     */
    public function user(): AuthenticatedUser
    {
        return $this->user;
    }

    /**
     * Vraci nebo zpracovava hodnotu actoridentity pro overeni identity
     */
    public function actorIdentity(): ActorIdentity
    {
        return ActorIdentity::local($this->user->id());
    }
}
