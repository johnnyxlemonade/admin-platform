<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Auth\Audit\AuthenticationAuditPresentationRegistrar;
use Lemonade\Admin\Auth\Http\Controller\ForgotPasswordController;
use Lemonade\Admin\Auth\Http\Controller\LoginController;
use Lemonade\Admin\Auth\Http\Controller\MfaController;
use Lemonade\Admin\Auth\Routing\AdminAuthRouteRegistrar;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje sluzby prihlaseni administrace
 */
final class AdminAuthServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
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
        $container->singleton(CurrentUserProvider::class, CurrentUserProvider::class);
        $container->singleton(CurrentPrincipalProviderInterface::class, static fn(ContainerInterface $container): CurrentUserProvider => $container->get(CurrentUserProvider::class));
        $container->scoped(AuthPageRenderer::class, AuthPageRenderer::class);
        $container->scoped(LoginController::class, LoginController::class);
        $container->scoped(MfaController::class, MfaController::class);
        $container->scoped(ForgotPasswordController::class, ForgotPasswordController::class);
    }

    /**
     * Registruje auth routy, resources a auditni presentation po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminAuthRouteRegistrar::class));
        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'admin.auth');
        $container->get(ViewResourceRegistry::class)->register('admin-auth', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('auth');
        $container->get(AuthenticationAuditPresentationRegistrar::class)->register();
    }
}
