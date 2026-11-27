<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Users;

use Lemonade\Admin\Http\Controller\ModuleController;
use Lemonade\Admin\Routing\AdminModuleRouteRegistrar;
use Lemonade\Admin\System\Users\Http\Controller\UsersProfileController;
use Lemonade\Admin\System\Users\Routing\UsersProfileRouteRegistrar;
use Lemonade\Admin\System\Users\UsersModuleDefinition;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Verifies canonical self-service profile route registration
 */
final class UsersProfileRouteRegistrarTest extends TestCase
{
    /**
     * Verifies canonical self-service URLs without a target user ID
     */
    public function testItRegistersProfilePageAndSaveTransports(): void
    {
        $router = $this->router();

        self::assertSame(UsersProfileController::class, $router->match(new ServerRequest('GET', '/admin/system/users/profile'))->controller());
        self::assertSame('profile', $router->match(new ServerRequest('GET', '/admin/system/users/profile'))->action());
        self::assertSame('security', $router->match(new ServerRequest('GET', '/admin/system/users/profile/security'))->action());
        self::assertSame('preferences', $router->match(new ServerRequest('GET', '/admin/system/users/profile/preferences'))->action());
        self::assertSame('activity', $router->match(new ServerRequest('GET', '/admin/system/users/profile/activity'))->action());
        self::assertSame('save', $router->match(new ServerRequest('POST', '/admin/system/users/profile'))->action());
        self::assertSame('changePassword', $router->match(new ServerRequest('POST', '/admin/system/users/profile/password'))->action());
        self::assertSame('ajaxSave', $router->match(new ServerRequest('POST', '/admin/system/users/profile/ajax'))->action());
    }

    /**
     * Verifies the Users edit URL resolves the self redirect before the management flow
     */
    public function testItClaimsOnlyTheUsersEditRouteBeforeGenericManagementTransport(): void
    {
        $match = $this->router()->match(new ServerRequest('GET', '/admin/system/users/edit/42'));

        self::assertSame(UsersProfileController::class, $match->controller());
        self::assertSame('edit', $match->action());
        self::assertNotSame(ModuleController::class, $match->controller());
    }

    /**
     * Builds a router with the Users registrar before generic System module routes
     */
    private function router(): Router
    {
        $router = new Router();
        $router->group('/admin', function (Router $router): void {
            (new UsersProfileRouteRegistrar())->registerRoutes($router);
            (new AdminModuleRouteRegistrar($this->modules()))->registerRoutes($router);
        });

        return $router;
    }

    /**
     * Returns the minimal Users registry required by the generic route registrar
     */
    private function modules(): \Lemonade\Admin\Module\AdminModuleRegistry
    {
        $registry = (new \ReflectionClass(\Lemonade\Admin\Module\AdminModuleRegistry::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(\Lemonade\Admin\Module\AdminModuleRegistry::class, 'definitions'))->setValue($registry, [
            'system.users' => new UsersModuleDefinition(),
        ]);

        return $registry;
    }
}
