<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Routing;

use Lemonade\Admin\Http\Controller\AdminErrorController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminErrorRouteRegistrar;
use Lemonade\Cms\Routing\Cms\PublicCmsRouteRegistrar;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AdminErrorRouteRegistrarTest extends TestCase
{
    public function testItCatchesUnknownAdminRoutesBeforeThePublicCmsFallback(): void
    {
        $router = new Router();
        $router->getNamed('admin.module.index', '/admin/{module}', ControllerAction::for('Example\\Controllers\\ModuleController', 'index'));

        $router->group('/admin', static function (Router $router): void {
            (new AdminErrorRouteRegistrar())->registerRoutes($router);
        });
        (new PublicCmsRouteRegistrar())->registerRoutes($router);

        $match = $router->match(new ServerRequest('GET', '/admin/languages/dsadasa'));

        self::assertSame(AdminErrorController::class, $match->controller());
        self::assertSame('notFound', $match->action());
        self::assertSame([AdminAuthenticationMiddleware::class], $match->middleware());
        self::assertSame('Example\\Controllers\\ModuleController', $router->match(new ServerRequest('GET', '/admin/languages'))->controller());
        self::assertSame(
            'Lemonade\\Cms\\Http\\Controller\\PublicCmsRouteController',
            $router->match(new ServerRequest('GET', '/aktuality/test'))->controller(),
        );
    }

    public function testItCatchesUnknownAdminPostRoutes(): void
    {
        $router = new Router();

        $router->group('/admin', static function (Router $router): void {
            (new AdminErrorRouteRegistrar())->registerRoutes($router);
        });

        $match = $router->match(new ServerRequest('POST', '/admin/some-module/ajax/123'));

        self::assertSame(AdminErrorController::class, $match->controller());
        self::assertSame('notFound', $match->action());
        self::assertSame([AdminAuthenticationMiddleware::class], $match->middleware());
    }

    public function testItIsRegisteredAfterConcreteAdminRoutesAndBeforeThePublicCmsFallback(): void
    {
        self::assertSame(9900, (new AdminErrorRouteRegistrar())->priority());
        self::assertLessThan((new PublicCmsRouteRegistrar())->priority(), (new AdminErrorRouteRegistrar())->priority());
    }
}
