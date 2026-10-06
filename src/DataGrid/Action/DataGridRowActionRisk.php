<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Action;

/**
 * Určuje míru rizika řádkové akce DataGridu.
 */
enum DataGridRowActionRisk: string
{
    case Normal = 'normal';
    case Warning = 'warning';
    case Destructive = 'destructive';
}
