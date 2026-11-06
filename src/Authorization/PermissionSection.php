<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

/**
 * Rozlisuje prezentacni sekce opravneni bez vlivu na jejich vyhodnoceni
 */
enum PermissionSection: string
{
    case System = 'system';
    case Modules = 'modules';
    case ContentTypes = 'content-types';
}
