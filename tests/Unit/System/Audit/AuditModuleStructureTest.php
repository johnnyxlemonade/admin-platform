<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Audit;

use PHPUnit\Framework\TestCase;

final class AuditModuleStructureTest extends TestCase
{
    public function testItUsesOnlyReadOnlyModuleCapabilities(): void
    {
        $root = dirname(__DIR__, 4) . '/src/System/Audit';

        self::assertFileExists($root . '/AuditModuleDefinition.php');
        self::assertFileExists($root . '/AuditModuleProvider.php');
        self::assertFileExists($root . '/AuditModulePageProvider.php');
        self::assertFileExists($root . '/Models/AuditLogModel.php');
        self::assertFileExists($root . '/DataGrid/AuditDataGrid.php');
        self::assertFileDoesNotExist($root . '/Resources/views/index.php');
        self::assertFileExists(dirname(__DIR__, 4) . '/src/Resources/views/datagrid/index.php');
        self::assertFileExists($root . '/Resources/lang/cs/audit.php');
        self::assertFileExists($root . '/Resources/lang/en/audit.php');
        self::assertFileDoesNotExist($root . '/Editor');
        self::assertFileDoesNotExist($root . '/Actions');
        self::assertFileDoesNotExist($root . '/Services');
        self::assertFileDoesNotExist($root . '/Policies');
    }
}
