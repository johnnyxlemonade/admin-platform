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

final class UsersProfileRouteRegistrarTest extends TestCase
{
    /**
     * Overuje canonical self-service URL bez ciloveho Users ID
     */
    public function testItRegistersProfilePageAndSaveTransports(): void
    {
        $router = $this->router();

        self::assertSame(UsersProfileController::class, $router->match(new ServerRequest('GET', '/admin/system/users/profile'))->controller());
        self::assertSame('profile', $router->match(new ServerRequest('GET', '/admin/system/users/profile'))->action());
        self::assertSame('save', $router->match(new ServerRequest('POST', '/admin/system/users/profile'))->action());
        self::assertSame('ajaxSave', $router->match(new ServerRequest('POST', '/admin/system/users/profile/ajax'))->action());
    }

    /**
     * Overuje, ze Users edit URL nejdriv rozhodne canonical self redirect a jinak deleguje management flow
     */
    public function testItClaimsOnlyTheUsersEditRouteBeforeGenericManagementTransport(): void
    {
        $match = $this->router()->match(new ServerRequest('GET', '/admin/system/users/edit/42'));

        self::assertSame(UsersProfileController::class, $match->controller());
        self::assertSame('edit', $match->action());
        self::assertNotSame(ModuleController::class, $match->controller());
    }

    /**
     * Sestavuje router se specific Users registrarem pred generic System module routou
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
     * Vraci minimalni registry Users segmentu pro generic route registrar
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
