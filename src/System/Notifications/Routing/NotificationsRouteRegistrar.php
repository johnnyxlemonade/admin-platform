<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Routing;

use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Admin\System\Notifications\Http\Controller\NotificationsAudienceOptionsController;
use Lemonade\Admin\System\Notifications\Http\Controller\NotificationsExportController;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje management routy exportu a voleb publika
 */
final class NotificationsRouteRegistrar implements AdminRouteRegistrarInterface
{
    public function id(): string
    {
        return 'system.notifications';
    }

    public function priority(): int
    {
        return 310;
    }

    /**
     * Pridava autentizovane endpointy a CSRF ochranu mutacniho exportu
     */
    public function registerRoutes(Router $router): void
    {
        $router->postNamed(
            name: 'admin.notifications.export',
            path: '/system/notifications/export',
            action: ControllerAction::for(
                controllerClass: NotificationsExportController::class,
                method: 'export',
            ),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        $router->getNamed(
            name: 'admin.notifications.audience.users',
            path: '/system/notifications/audience/users',
            action: ControllerAction::for(
                controllerClass: NotificationsAudienceOptionsController::class,
                method: 'users',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
    }
}
