<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Action;

/**
 * Určuje způsob provedení řádkové akce DataGridu.
 */
enum DataGridRowActionKind: string
{
    case Navigate = 'navigate';
    case Modal = 'modal';
    case Mutation = 'mutation';
}
