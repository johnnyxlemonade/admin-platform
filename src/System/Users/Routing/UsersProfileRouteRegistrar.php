<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Routing;

use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Admin\System\Users\Http\Controller\UsersProfileController;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registers the current user's profile routes
 */
final readonly class UsersProfileRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Returns the registrar identifier
     */
    public function id(): string
    {
        return 'system.users.profile';
    }

    /**
     * Returns the route registration priority
     */
    public function priority(): int
    {
        return 290;
    }

    /**
     * Registers profile section and mutation routes
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.system.users.profile',
            path: '/system/users/profile',
            action: ControllerAction::for(UsersProfileController::class, 'profile'),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->getNamed(
            name: 'admin.system.users.profile.security',
            path: '/system/users/profile/security',
            action: ControllerAction::for(UsersProfileController::class, 'security'),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->getNamed(
            name: 'admin.system.users.profile.preferences',
            path: '/system/users/profile/preferences',
            action: ControllerAction::for(UsersProfileController::class, 'preferences'),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->getNamed(
            name: 'admin.system.users.profile.activity',
            path: '/system/users/profile/activity',
            action: ControllerAction::for(UsersProfileController::class, 'activity'),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->postNamed(
            name: 'admin.system.users.profile.save',
            path: '/system/users/profile',
            action: ControllerAction::for(UsersProfileController::class, 'save'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        $router->postNamed(
            name: 'admin.system.users.profile.password',
            path: '/system/users/profile/password',
            action: ControllerAction::for(UsersProfileController::class, 'changePassword'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        $router->postNamed(
            name: 'admin.system.users.profile.ajax',
            path: '/system/users/profile/ajax',
            action: ControllerAction::for(UsersProfileController::class, 'ajaxSave'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        $router->getNamed(
            name: 'admin.system.users.edit',
            path: '/system/users/edit/{id}',
            action: ControllerAction::for(UsersProfileController::class, 'edit'),
        )->middleware(AdminAuthenticationMiddleware::class);
    }
}
