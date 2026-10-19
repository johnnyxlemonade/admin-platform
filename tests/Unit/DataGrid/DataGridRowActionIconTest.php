<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\DataGrid\Action\DataGridRowActionIcon;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

/**
 * Overuje sdilene ikony akci radku DataGridu
 */
final class DataGridRowActionIconTest extends TestCase
{
    /**
     * Akce poradi pouzivaji smerove ikony
     */
    public function testMoveActionsUseDirectionalIcons(): void
    {
        self::assertSame(AdminIcon::ArrowUp, DataGridRowActionIcon::forKey('move-up'));
        self::assertSame(AdminIcon::ArrowDown, DataGridRowActionIcon::forKey('move-down'));
    }
}
