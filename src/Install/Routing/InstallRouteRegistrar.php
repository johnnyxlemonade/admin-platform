<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install\Routing;

use Lemonade\Admin\Http\Middleware\AdminLoginAjaxCsrfMiddleware;
use Lemonade\Admin\Install\Http\Controller\InstallController;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje routy potrebne pro instalaci aplikace
 */
final class InstallRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vraci stabilni identifikator registratoru rout
     */
    public function id(): string
    {
        return 'admin.install';
    }

    /**
     * Vraci poradi registrace instalacnich rout
     */
    public function priority(): int
    {
        return 90;
    }

    /**
     * Registruje HTTP routy instalace
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.install',
            path: '/install',
            action: ControllerAction::for(
                controllerClass: InstallController::class,
                method: 'index',
            ),
        );

        $router->postNamed(
            name: 'admin.install.run',
            path: '/install',
            action: ControllerAction::for(
                controllerClass: InstallController::class,
                method: 'run',
            ),
        )->middleware(
            AdminLoginAjaxCsrfMiddleware::class,
            CsrfMiddleware::class,
        );
    }
}
