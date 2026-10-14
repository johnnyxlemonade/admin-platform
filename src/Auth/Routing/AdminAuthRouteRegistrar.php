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
final readonly class AdminAuthRouteRegistrar implements AdminRouteRegistrarInterface
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
            ->middleware(AdminAnonymousMiddleware::class, AdminLoginAjaxCsrfMiddleware::class, CsrfMiddleware::class);
        $router->getNamed(name: 'admin.login.mfa.enroll', path: '/login/mfa/enroll', action: ControllerAction::for(controllerClass: MfaController::class, method: 'loginEnrollment'))
            ->middleware(AdminAnonymousMiddleware::class);
        $router->postNamed(name: 'admin.login.mfa.enroll.start', path: '/login/mfa/enroll/start', action: ControllerAction::for(controllerClass: MfaController::class, method: 'startLoginEnrollment'))
            ->middleware(AdminAnonymousMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.login.mfa.enroll.confirm', path: '/login/mfa/enroll/confirm', action: ControllerAction::for(controllerClass: MfaController::class, method: 'confirmLoginEnrollment'))
            ->middleware(AdminAnonymousMiddleware::class, CsrfMiddleware::class);

        $router->getNamed(name: 'admin.mfa.modal', path: '/mfa/modal', action: ControllerAction::for(controllerClass: MfaController::class, method: 'modal'))
            ->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(name: 'admin.mfa.modal.reauth', path: '/mfa/modal/reauth', action: ControllerAction::for(controllerClass: MfaController::class, method: 'reauthenticateModal'))
            ->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.mfa.modal.enroll.start', path: '/mfa/modal/enroll/start', action: ControllerAction::for(controllerClass: MfaController::class, method: 'startModalEnrollment'))
            ->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.mfa.modal.enroll.confirm', path: '/mfa/modal/enroll/confirm', action: ControllerAction::for(controllerClass: MfaController::class, method: 'confirmModalEnrollment'))
            ->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(name: 'admin.mfa.modal.disable', path: '/mfa/modal/disable', action: ControllerAction::for(controllerClass: MfaController::class, method: 'disableModal'))
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
