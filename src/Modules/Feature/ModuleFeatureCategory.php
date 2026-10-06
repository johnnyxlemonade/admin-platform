<?php

declare(strict_types=1);

namespace Lemonade\Admin\Modules\Feature;

/**
 * Urcuje kategorii volitelne funkce modulu
 */
enum ModuleFeatureCategory: string
{
    case AdminCapability = 'admin_capability';
    case StructuralCapability = 'structural_capability';
    case Toggle = 'toggle';
}
