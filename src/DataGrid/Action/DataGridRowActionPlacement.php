<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Action;

/**
 * Určuje vizuální umístění řádkové akce DataGridu.
 */
enum DataGridRowActionPlacement: string
{
    case Inline = 'inline';
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Destructive = 'destructive';
}
