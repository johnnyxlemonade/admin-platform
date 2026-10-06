<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Auth\Audit\AuthenticationAuditPresentationRegistrar;
use Lemonade\Admin\Auth\Http\Controller\ForgotPasswordController;
use Lemonade\Admin\Auth\Http\Controller\LoginController;
use Lemonade\Admin\Auth\Http\Middleware\AdminAnonymousMiddleware;
use Lemonade\Admin\Auth\Oidc\OidcAdmissionPolicy;
use Lemonade\Admin\Auth\Oidc\OidcAuthenticationService;
use Lemonade\Admin\Auth\Oidc\OidcAuthorizationTransactionStore;
use Lemonade\Admin\Auth\Oidc\OidcLogoutService;
use Lemonade\Admin\Auth\Oidc\OidcShadowUserProvisioner;
use Lemonade\Admin\Auth\Routing\AdminAuthRouteRegistrar;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Http\Middleware\AdminLoginAjaxCsrfMiddleware;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje sluzby prihlaseni administrace
 */
final class AdminAuthServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Urcuje sdilenou Admin infrastrukturu potrebnou pro prihlaseni
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zaregistruje sluzby, routy a zdroje prihlaseni administrace
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(OidcAuthorizationTransactionStore::class, OidcAuthorizationTransactionStore::class);
        $container->singleton(OidcAdmissionPolicy::class, OidcAdmissionPolicy::class);
        $container->singleton(OidcShadowUserProvisioner::class, OidcShadowUserProvisioner::class);
        $container->singleton(OidcAuthenticationService::class, OidcAuthenticationService::class);
        $container->singleton(OidcLogoutService::class, OidcLogoutService::class);
        $container->singleton(AuthenticationService::class, AuthenticationService::class);
        $container->singleton(AuthenticationAuditPresentationRegistrar::class, AuthenticationAuditPresentationRegistrar::class);
        $container->singleton(CurrentUserProvider::class, CurrentUserProvider::class);
        $container->singleton(CurrentPrincipalProviderInterface::class, static fn(ContainerInterface $container): CurrentUserProvider => $container->get(CurrentUserProvider::class));
        $container->scoped(AuthPageRenderer::class, AuthPageRenderer::class);
        $container->singleton(AdminAuthenticationMiddleware::class, AdminAuthenticationMiddleware::class);
        $container->singleton(AdminAnonymousMiddleware::class, AdminAnonymousMiddleware::class);
        $container->singleton(AdminLoginAjaxCsrfMiddleware::class, AdminLoginAjaxCsrfMiddleware::class);
        $container->scoped(LoginController::class, LoginController::class);
        $container->scoped(ForgotPasswordController::class, ForgotPasswordController::class);
        $container->singleton(AdminAuthRouteRegistrar::class, AdminAuthRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminAuthRouteRegistrar::class));
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'admin.auth');
        $container->get(ViewResourceRegistry::class)->register('admin-auth', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('auth');
        $container->get(AuthenticationAuditPresentationRegistrar::class)->register();
    }
}
