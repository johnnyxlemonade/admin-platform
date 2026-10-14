<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

/**
 * Vymezuje serverem odvozeny stav administracni relace
 */
enum AdminSessionState: string
{
    case ACTIVE = 'active';
    case LOCKED = 'locked';
    case EXPIRED = 'expired';
    case UNAUTHENTICATED = 'unauthenticated';
}
