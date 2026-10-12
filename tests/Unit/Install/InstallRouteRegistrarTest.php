<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Install;

use Lemonade\Admin\Install\Http\Controller\InstallController;
use Lemonade\Admin\Install\Routing\InstallRouteRegistrar;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Overuje oddelene HTTP vstupy initial instalace a upgrade workflow
 */
final class InstallRouteRegistrarTest extends TestCase
{
    /**
     * Overuje, ze installer a upgrade maji samostatne route names a controllery
     */
    public function testItRegistersSeparateInitialInstallAndUpgradeFlows(): void
    {
        $router = new Router();
        $router->group('/admin', static function (Router $router): void {
            (new InstallRouteRegistrar())->registerRoutes($router);
        });

        self::assertSame(InstallController::class, $router->match(new ServerRequest('GET', '/admin/install'))->controller());
        self::assertSame('index', $router->match(new ServerRequest('GET', '/admin/install'))->action());
        self::assertSame('run', $router->match(new ServerRequest('POST', '/admin/install'))->action());
        self::assertSame('upgrade', $router->match(new ServerRequest('GET', '/admin/upgrade'))->action());
        self::assertSame('runUpgrade', $router->match(new ServerRequest('POST', '/admin/upgrade'))->action());
    }
}
