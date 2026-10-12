<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\System\Users\Http\Controller\UsersProfileController;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class UsersProfileControllerTest extends TestCase
{
    /**
     * Overuje, ze profile controller ignoruje potencialni cilove ID a pouziva session identitu
     */
    public function testItResolvesProfileTargetOnlyFromCurrentSessionUser(): void
    {
        $principals = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principals->method('currentUser')->willReturn(new AuthenticatedUser(17, 'profile@example.test'));
        $controller = (new \ReflectionClass(UsersProfileController::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(UsersProfileController::class, 'currentUser'))->setValue($controller, $principals);
        $method = new \ReflectionMethod(UsersProfileController::class, 'currentUserId');

        self::assertSame(17, $method->invoke($controller, (new ServerRequest('POST', '/admin/system/users/profile'))->withParsedBody(['id' => 99])));
    }
}
