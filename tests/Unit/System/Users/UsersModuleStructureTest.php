<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use PHPUnit\Framework\TestCase;

final class UsersModuleStructureTest extends TestCase
{
    public function testInternalUsersImplementationsUseResponsibilityNamespaces(): void
    {
        $root = dirname(__DIR__, 4) . '/src/System/Users';

        self::assertFileExists($root . '/Models/UserModel.php');
        self::assertFileExists($root . '/Models/UserRoleModel.php');
        self::assertFileExists($root . '/Models/UserPermissionOverrideModel.php');
        self::assertFileExists($root . '/Services/UserService.php');
        self::assertFileExists($root . '/DataGrid/UsersDataGrid.php');
        self::assertFileExists($root . '/Editor/UsersEditor.php');
        self::assertFileExists($root . '/Policies/UsersEditorAccessPolicy.php');
        self::assertFileExists($root . '/Exceptions/UserNotFoundException.php');
        self::assertFileDoesNotExist($root . '/UserService.php');
        self::assertFileDoesNotExist($root . '/UserModel.php');
        self::assertFileDoesNotExist($root . '/UsersEditor.php');
    }
}
