<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Field;

/**
 * Vyjmenovava podporovane typy nativniho textoveho inputu
 */
enum NativeInputType: string
{
    case Text = 'text';
    case Email = 'email';
    case Tel = 'tel';
    case Number = 'number';
    case DatetimeLocal = 'datetime-local';
}
