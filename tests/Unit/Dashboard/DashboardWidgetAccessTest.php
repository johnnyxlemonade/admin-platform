<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use InvalidArgumentException;
use Lemonade\Admin\Dashboard\DashboardWidgetAccess;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetAccessTest extends TestCase
{
    public function testPermissionAccessCarriesItsPermission(): void
    {
        $access = DashboardWidgetAccess::permission('system.users.view');

        self::assertSame('system.users.view', $access->permissionCode());
    }

    public function testAuthenticatedAccessCarriesNoPermission(): void
    {
        $access = DashboardWidgetAccess::authenticated();

        self::assertNull($access->permissionCode());
    }

    public function testPermissionAccessRejectsAnEmptyPermission(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('require a permission');

        DashboardWidgetAccess::permission('');
    }
}
