<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Urcuje hranice pristupu k cizim uzivatelskym zaznamum
 */
final class AuthorizationTargetPolicy
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function canTargetUser(AuthenticatedUser $actor, AuthenticatedUser $target): bool
    {
        if ($this->authorization->isSuperAdmin($actor)) {
            return true;
        }
        if ($this->authorization->isSuperAdmin($target)) {
            return false;
        }
        $actorPermissions = array_fill_keys($this->authorization->effectivePermissionsForUser($actor), true);
        foreach ($this->authorization->effectivePermissionsForUser($target) as $permission) {
            if (!isset($actorPermissions[$permission])) {
                return false;
            }
        }

        return true;
    }
}
