<?php

declare(strict_types=1);

namespace Lemonade\Admin\Install;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Install\Http\Controller\InstallController;
use Lemonade\Admin\Install\Routing\InstallRouteRegistrar;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\View\ViewResourceRegistry;

/**
 * Registruje lifecycle instalace administrace, jeji UI a HTTP endpointy
 */
final class AdminInstallationServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Urcuje sdilenou infrastrukturu potrebnou pro instalacni lifecycle
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zapisuje instalacni sluzbu, controller, routy a resource roots
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(InstallationService::class, InstallationService::class);
        $container->singleton(
            InstallationStateInterface::class,
            static fn(ContainerInterface $container): InstallationService => $container->get(InstallationService::class),
        );
        $container->scoped(InstallController::class, InstallController::class);
        $container->singleton(InstallRouteRegistrar::class, InstallRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(InstallRouteRegistrar::class));

        $container->get(TranslationResourceRegistry::class)->register(__DIR__ . '/Resources/lang', 'admin.install');
        $container->get(ViewResourceRegistry::class)->register('admin-install', __DIR__ . '/Resources/views');
        $container->get(ClientTranslationGroupRegistry::class)->register('install');
    }
}
