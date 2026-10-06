<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\Routing;

use Lemonade\Admin\Editor\Http\Controller\EditorModalController;
use Lemonade\Admin\Editor\Routing\EditorModalRouteRegistrar;
use Lemonade\Admin\Http\Controller\AdminErrorController;
use Lemonade\Admin\Routing\AdminErrorRouteRegistrar;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class EditorModalRouteRegistrarTest extends TestCase
{
    /**
     * Overuje canonical create a edit URL shared modalniho transportu
     */
    public function testItRegistersGenericModalCreateAndEditRoutes(): void
    {
        $router = $this->router();

        $create = $router->match(new ServerRequest('GET', '/admin/api/modal/languages/create'));
        $edit = $router->match(new ServerRequest('GET', '/admin/api/modal/languages/15/edit'));

        self::assertSame(EditorModalController::class, $create->controller());
        self::assertSame('create', $create->action());
        self::assertSame(EditorModalController::class, $edit->controller());
        self::assertSame('edit', $edit->action());
    }

    /**
     * Overuje, ze legacy system modal route neni registrovana
     */
    public function testItDoesNotRegisterLegacySystemModalRoutes(): void
    {
        $match = $this->router()->match(new ServerRequest('GET', '/admin/system/languages/create/modal'));

        self::assertSame(AdminErrorController::class, $match->controller());
        self::assertSame('notFound', $match->action());
    }

    /**
     * Registruje shared transport s fallbackem pro nezname Admin URL
     */
    private function router(): Router
    {
        $router = new Router();
        $router->group('/admin', static function (Router $router): void {
            (new EditorModalRouteRegistrar())->registerRoutes($router);
            (new AdminErrorRouteRegistrar())->registerRoutes($router);
        });

        return $router;
    }
}
