<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\System\Users\Editor\UserEditorInput;
use PHPUnit\Framework\TestCase;

final class UserEditorInputTest extends TestCase
{
    public function testItKeepsEverySubmittedRequestedPermissionState(): void
    {
        $input = UserEditorInput::fromValidated([
            'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.test',
            'active' => '1', 'role' => '2',
            'permissions' => ['system.users.view' => '1', 'system.users.edit' => '0'],
        ]);

        self::assertSame(['system.users.view' => true, 'system.users.edit' => false], $input->permissionStates());
        self::assertSame(1, $input->version());
    }

    public function testItKeepsTheExpectedRecordVersionForCompareAndSwap(): void
    {
        $input = UserEditorInput::fromValidated([
            'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.test',
            'active' => '1', 'version' => '5',
        ]);

        self::assertSame(5, $input->version());
    }
}
