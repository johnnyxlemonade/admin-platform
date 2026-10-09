<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Routing;

use Lemonade\Admin\Auth\Http\Controller\ForgotPasswordController;
use Lemonade\Admin\Auth\Http\Controller\LoginController;
use Lemonade\Admin\Auth\Http\Controller\MfaController;
use Lemonade\Admin\Auth\Http\Middleware\AdminAnonymousMiddleware;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Http\Middleware\AdminLoginAjaxCsrfMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje routy prihlaseni administrace
 */
final class AdminAuthRouteRegistrar implements AdminRouteRegistrarInterface
{
    public function id(): string
    {
        return 'admin.auth';
    }

    public function priority(): int
    {
        return 100;
    }

    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.login',
            path: '/login',
            action: ControllerAction::for(
                controllerClass: LoginController::class,
                method: 'form',
            ),
        )->middleware(AdminAnonymousMiddleware::class);

        $router->getNamed(
            name: 'admin.login.keycloak',
            path: '/login/keycloak',
            action: ControllerAction::for(
                controllerClass: LoginController::class,
                method: 'keycloak',
            ),
        )->middleware(AdminAnonymousMiddleware::class);

        $router->getNamed(
            name: 'admin.auth.keycloak.callback',
            path: '/auth/keycloak/callback',
            action: ControllerAction::for(
                controllerClass: LoginController::class,
                method: 'keycloakCallback',
            ),
        );
        $router->postNamed(
            name: 'admin.login.submit',
            path: '/login',
            action: ControllerAction::for(
                controllerClass: LoginController::class,
                method: 'login',
            ),
        )->middleware(
            AdminAnonymousMiddleware::class,
            AdminLoginAjaxCsrfMiddleware::class,
            CsrfMiddleware::class,
        );

        $router->getNamed(name: 'admin.login.mfa', path: '/login/mfa', action: ControllerAction::for(controllerClass: MfaController::class, method: 'challenge'))
            ->middleware(AdminAnonymousMiddleware::class);
        $router->postNamed(name: 'admin.login.mfa.verify', path: '/login/mfa', action: ControllerAction::for(controllerClass: MfaController::class, method: 'verifyChallenge'))
            ->middleware(AdminAnonymousMiddleware::class, CsrfMiddleware::class);
        $router->getNamed(name: 'admin.login.mfa.enroll', path: '/login/mfa/enroll', action: ControllerAction::for(controllerClass: MfaController::class, method: 'loginEnrollment'))
            ->middleware(AdminAnonymousMiddleware::class);
        $router->postNamed(name: 'admin.login.mfa.enroll.start', path: '/login/mfa/enroll/start', action: ControllerAction::for(controllerClass: MfaController::class, method: 'startLoginEnrollment'))
            ->middleware(AdminAnonymousMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.login.mfa.enroll.confirm', path: '/login/mfa/enroll/confirm', action: ControllerAction::for(controllerClass: MfaController::class, method: 'confirmLoginEnrollment'))
            ->middleware(AdminAnonymousMiddleware::class, CsrfMiddleware::class);

        $router->getNamed(name: 'admin.profile.mfa', path: '/profile/mfa', action: ControllerAction::for(controllerClass: MfaController::class, method: 'profile'))
            ->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(name: 'admin.profile.mfa.reauth', path: '/profile/mfa/reauth', action: ControllerAction::for(controllerClass: MfaController::class, method: 'reauthenticate'))
            ->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.profile.mfa.enroll.start', path: '/profile/mfa/enroll/start', action: ControllerAction::for(controllerClass: MfaController::class, method: 'startProfileEnrollment'))
            ->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.profile.mfa.enroll.confirm', path: '/profile/mfa/enroll/confirm', action: ControllerAction::for(controllerClass: MfaController::class, method: 'confirmProfileEnrollment'))
            ->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.profile.mfa.disable', path: '/profile/mfa/disable', action: ControllerAction::for(controllerClass: MfaController::class, method: 'disableProfile'))
            ->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        $router->getNamed(
            name: 'admin.login.forgot',
            path: '/login/forgot',
            action: ControllerAction::for(
                controllerClass: ForgotPasswordController::class,
                method: 'form',
            ),
        )->middleware(AdminAnonymousMiddleware::class);
        $router->postNamed(
            name: 'admin.login.forgot.submit',
            path: '/login/forgot',
            action: ControllerAction::for(
                controllerClass: ForgotPasswordController::class,
                method: 'submit',
            ),
        )->middleware(
            AdminAnonymousMiddleware::class,
            CsrfMiddleware::class,
        );

        $router->postNamed(
            name: 'admin.logout',
            path: '/logout',
            action: ControllerAction::for(
                controllerClass: LoginController::class,
                method: 'logout',
            ),
        )->middleware(
            AdminAuthenticationMiddleware::class,
            CsrfMiddleware::class,
        );
    }
}
