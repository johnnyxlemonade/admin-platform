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
 * Registruje profilove routy uzivatele
 */
final class UsersProfileRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vraci identifikator registraru
     */
    public function id(): string
    {
        return 'system.users.profile';
    }

    /**
     * Vraci prioritu registrace rout
     */
    public function priority(): int
    {
        return 290;
    }

    /**
     * Registruje profilove routy
     */
    public function registerRoutes(Router $router): void
    {
        // Zobrazi profil aktualniho uzivatele
        $router->getNamed(
            name: 'admin.system.users.profile',
            path: '/system/users/profile',
            action: ControllerAction::for(UsersProfileController::class, 'profile'),
        )->middleware(AdminAuthenticationMiddleware::class);

        // Ulozi profil
        $router->postNamed(
            name: 'admin.system.users.profile.save',
            path: '/system/users/profile',
            action: ControllerAction::for(UsersProfileController::class, 'save'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        // Ulozi profil pres AJAX
        $router->postNamed(
            name: 'admin.system.users.profile.ajax',
            path: '/system/users/profile/ajax',
            action: ControllerAction::for(UsersProfileController::class, 'ajaxSave'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        // Presmeruje vlastni editaci na profil
        $router->getNamed(
            name: 'admin.system.users.edit',
            path: '/system/users/edit/{id}',
            action: ControllerAction::for(UsersProfileController::class, 'edit'),
        )->middleware(AdminAuthenticationMiddleware::class);
    }
}
