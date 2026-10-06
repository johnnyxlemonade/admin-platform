<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use Lemonade\Admin\Http\Controller\AdminErrorController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;

/**
 * Zachytava neznamou administracni cestu pred verejnym CMS fallbackem
 */
final class AdminErrorRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vrati stabilni identifikator registrace rout
     */
    public function id(): string
    {
        return 'admin.error-fallback';
    }

    /**
     * Vrati prioritu pred verejnym fallbackem
     */
    public function priority(): int
    {
        return 9900;
    }

    /**
     * Zaregistruje chraneny fallback pro nezname admin cesty
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.error.not-found',
            path: '/{path:any}',
            action: ControllerAction::for(
                controllerClass: AdminErrorController::class,
                method: 'notFound',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->post(
            path: '/{path:any}',
            action: ControllerAction::for(
                controllerClass: AdminErrorController::class,
                method: 'notFound',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
    }
}
