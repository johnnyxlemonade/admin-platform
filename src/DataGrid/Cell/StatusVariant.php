<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

/**
 * Urcuje vzhled stavove bunky
 */
enum StatusVariant: string
{
    case Success = 'status-success';
    case Muted = 'status-muted';
}
