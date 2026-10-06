<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Routing;

use Lemonade\Admin\Dashboard\Http\Controller\DashboardApiController;
use Lemonade\Admin\Dashboard\Http\Controller\DashboardController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje routy dashboardu
 */
final class DashboardRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vrati stabilni identifikator registrace rout
     */
    public function id(): string
    {
        return 'admin.dashboard.widgets';
    }

    /**
     * Vrati prioritu rout dashboardu
     */
    public function priority(): int
    {
        return 500;
    }

    /**
     * Zaregistruje chranenou stranku a API routy dashboardu
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.dashboard',
            path: '/',
            action: ControllerAction::for(
                controllerClass: DashboardController::class,
                method: 'index',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
        $router->getNamed(
            name: 'admin.api.dashboard',
            path: '/api/dashboard',
            action: ControllerAction::for(
                controllerClass: DashboardApiController::class,
                method: 'dashboard',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->getNamed(
            name: 'admin.api.dashboard.widget',
            path: '/api/dashboard/widgets/{widgetCode}',
            action: ControllerAction::for(
                controllerClass: DashboardApiController::class,
                method: 'content',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->postNamed(
            name: 'admin.dashboard.widgets.ajax',
            path: '/dashboard/widgets/ajax',
            action: ControllerAction::for(
                controllerClass: DashboardApiController::class,
                method: 'action',
            ),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }
}
