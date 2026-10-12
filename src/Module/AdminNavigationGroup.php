<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

/**
 * Urcuje canonical oblasti navigace administracnich modulu
 */
enum AdminNavigationGroup: string
{
    case System = 'system';
    case Content = 'content';
    case Settings = 'settings';
}
