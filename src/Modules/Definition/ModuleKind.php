<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Definition;

/**
 * Urcuje druh modulu
 */
enum ModuleKind: string
{
    case System = 'system';
    case Optional = 'optional';
}
