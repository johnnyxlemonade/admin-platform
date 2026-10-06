<?php

declare(strict_types=1);

namespace Lemonade\Admin\Identity;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;

final class LocalActorGuard
{
    public function __construct(private readonly CurrentPrincipalProviderInterface $principals) {}

    public function requireLocalUser(): AuthenticatedUser
    {
        $identity = $this->principals->currentPrincipal()?->actorIdentity();
        $user = $this->principals->currentUser();

        if ($identity === null || $user === null || $user->id() !== $identity->localUserId()) {
            throw new LocalActorRequiredException();
        }

        return $user;
    }
}
