<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Authorization\PermissionOverrideNormalizer;
use Lemonade\Admin\System\Users\Editor\UserEditorInput;
use PHPUnit\Framework\TestCase;

final class UsersEditorPermissionPayloadContractTest extends TestCase
{
    public function testEditorPayloadReturningToRoleAllowNormalizesToNoStoredOverride(): void
    {
        $input = UserEditorInput::fromValidated([
            'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.test',
            'active' => '1', 'role' => '2', 'permissions' => ['system.users.edit' => '1'],
        ]);

        self::assertSame([], (new PermissionOverrideNormalizer())->normalize(
            ['system.users.edit'],
            $input->permissionStates() ?? [],
        ));
    }

    public function testEditorPayloadReturningToRoleDenyNormalizesToNoStoredOverride(): void
    {
        $input = UserEditorInput::fromValidated([
            'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.test',
            'active' => '1', 'role' => '2', 'permissions' => ['system.users.edit' => '0'],
        ]);

        self::assertSame([], (new PermissionOverrideNormalizer())->normalize([], $input->permissionStates() ?? []));
    }
}
