<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

/**
 * Urcuje rezim predavani opravneni
 */
enum PermissionDelegation: string
{
    case Normally = 'normally';
    case SuperAdminOnly = 'superadmin_only';
}
