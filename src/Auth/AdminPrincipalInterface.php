<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Identity\ActorIdentity;

/**
 * Urcuje kontrakt pro overeni nebo poskytnuti identity
 */
interface AdminPrincipalInterface
{
    /**
     * Vraci nebo zpracovava hodnotu actoridentity pro overeni identity
     */
    public function actorIdentity(): ActorIdentity;
}
