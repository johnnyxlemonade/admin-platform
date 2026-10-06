<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification\Routing;

use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Notification\Http\Controller\NotificationController;
use Lemonade\Admin\Notification\Http\Controller\NotificationsController;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje routy administracnich oznameni
 */
final class NotificationRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vrati stabilni identifikator registraru osobnich oznameni
     */
    public function id(): string
    {
        return 'admin.notifications';
    }

    /**
     * Urci poradi registrace route osobnich oznameni
     */
    public function priority(): int
    {
        return 300;
    }

    /**
     * Registruje autorizovane stranky, API a read mutace osobnich oznameni
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.notifications',
            path: '/notifications',
            action: ControllerAction::for(
                controllerClass: NotificationsController::class,
                method: 'index',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->getNamed(
            name: 'admin.api.notifications',
            path: '/api/notifications',
            action: ControllerAction::for(
                controllerClass: NotificationController::class,
                method: 'index',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->getNamed(
            name: 'admin.api.notifications.indicator',
            path: '/api/notifications/indicator',
            action: ControllerAction::for(
                controllerClass: NotificationController::class,
                method: 'indicator',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->postNamed(
            name: 'admin.notifications.ajax',
            path: '/notifications/ajax',
            action: ControllerAction::for(
                controllerClass: NotificationController::class,
                method: 'action',
            ),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }
}
