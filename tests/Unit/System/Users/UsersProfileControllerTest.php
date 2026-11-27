<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\System\Users\Http\Controller\UsersProfileController;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the self-profile controller identity boundary
 */
final class UsersProfileControllerTest extends TestCase
{
    /**
     * Verifies the profile target is resolved only from the current session identity
     */
    public function testItResolvesProfileTargetOnlyFromCurrentSessionUser(): void
    {
        $principals = self::createStub(CurrentPrincipalProviderInterface::class);
        $principals->method('currentUser')->willReturn(new AuthenticatedUser(17, 'profile@example.test'));
        $controller = (new \ReflectionClass(UsersProfileController::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(UsersProfileController::class, 'currentUser'))->setValue($controller, $principals);
        $method = new \ReflectionMethod(UsersProfileController::class, 'currentUserId');

        self::assertSame(17, $method->invoke($controller));
    }
}
