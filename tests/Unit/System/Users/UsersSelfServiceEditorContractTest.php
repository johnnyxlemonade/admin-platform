<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Action\Contract\ModuleActionAccessPolicyInterface;
use Lemonade\Admin\Action\Contract\ModuleActionLockBypassPolicyInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Editor\Contract\EditorAccessPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorLockBypassPolicyInterface;
use Lemonade\Admin\System\Users\Actions\UsersEditorSaveAction;
use Lemonade\Admin\System\Users\Editor\UsersEditor;
use Lemonade\Admin\System\Users\Policies\UsersEditorAccessPolicy;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

final class UsersSelfServiceEditorContractTest extends TestCase
{
    public function testUsersOwnsTheNarrowSelfServiceEditorException(): void
    {
        $editorInterfaces = class_implements(UsersEditor::class);
        $actionInterfaces = class_implements(UsersEditorSaveAction::class);

        self::assertIsArray($editorInterfaces);
        self::assertIsArray($actionInterfaces);
        self::assertContains(EditorAccessPolicyInterface::class, $editorInterfaces);
        self::assertContains(EditorLockBypassPolicyInterface::class, $editorInterfaces);
        self::assertContains(ModuleActionAccessPolicyInterface::class, $actionInterfaces);
        self::assertContains(ModuleActionLockBypassPolicyInterface::class, $actionInterfaces);

    }

    public function testOnlyTheCurrentUsersSafeProfilePayloadCanBypassAForeignLock(): void
    {
        $principal = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principal->method('currentUser')->willReturn(new AuthenticatedUser(2, 'user@example.test'));
        $authorization = new AuthorizationService(
            $principal,
            new Database(
                $this->createMock(ConnectionInterface::class),
                $this->createMock(DatabaseDriverInterface::class),
            ),
        );
        $policy = new UsersEditorAccessPolicy($authorization);

        self::assertTrue($policy->canBypassForeignLock(2));
        self::assertFalse($policy->canBypassForeignLock(3));
        self::assertTrue($policy->canSaveWithoutLock(2, [
            'first_name' => 'User',
            'last_name' => 'Example',
            'email' => 'user@example.test',
            'phone' => '',
            'active' => '1',
            'version' => '5',
        ]));
        self::assertTrue($policy->canSaveWithoutLock(2, [
            'first_name' => 'User',
            'last_name' => 'Example',
            'email' => 'user@example.test',
            'phone' => '',
            'active' => '1',
            'version' => '5',
            'local_password' => 'secure-password',
        ]));
        self::assertFalse($policy->canSaveWithoutLock(2, ['active' => '0', 'version' => '5']));
        self::assertFalse($policy->canSaveWithoutLock(2, ['active' => '1', 'version' => '5', 'role' => '1']));
        self::assertFalse($policy->canSaveWithoutLock(2, ['active' => '1', 'version' => '5', 'permissions' => []]));
    }
}
