<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use Lemonade\Admin\Http\Controller\TranslationResourceController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Navigation\Http\Controller\NavigationTranslationController;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;

/**
 * Registruje spolecne routy lokalizacnich zdroju administrace
 */
final class AdminRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vrati stabilni identifikator registrace rout
     */
    public function id(): string
    {
        return 'admin.core';
    }

    /**
     * Vrati prioritu zakladnich admin rout
     */
    public function priority(): int
    {
        return 200;
    }

    /**
     * Zaregistruje routy lokalizacnich zdroju administrace
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.resources.i18n.index',
            path: '/resources/i18n',
            action: ControllerAction::for(
                controllerClass: TranslationResourceController::class,
                method: 'index',
            ),
        );
        $router->getNamed(
            name: 'admin.resources.i18n.navigation',
            path: '/resources/i18n/navigation',
            action: ControllerAction::for(
                controllerClass: NavigationTranslationController::class,
                method: 'index',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
        $router->getNamed(
            name: 'admin.resources.i18n',
            path: '/resources/i18n/{group}',
            action: ControllerAction::for(
                controllerClass: TranslationResourceController::class,
                method: 'show',
            ),
        );
    }
}
