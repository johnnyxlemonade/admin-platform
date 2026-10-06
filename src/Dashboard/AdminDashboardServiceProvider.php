<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Dashboard\Contract\DashboardWidgetProviderInterface;
use Lemonade\Admin\Dashboard\Http\Controller\DashboardApiController;
use Lemonade\Admin\Dashboard\Http\Controller\DashboardController;
use Lemonade\Admin\Dashboard\Models\DashboardWidgetPreferenceModel;
use Lemonade\Admin\Dashboard\Routing\DashboardRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\TaggedServicesInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use RuntimeException;

/**
 * Registruje dashboardovou capability a jeji tagged widget composition
 */
final class AdminDashboardServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Urcuje sdilenou infrastrukturu potrebnou pro dashboard
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zapisuje dashboardove sluzby, controllery a route registrar
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(
            DashboardWidgetRegistry::class,
            static function (ContainerInterface $container): DashboardWidgetRegistry {
                if (!$container instanceof TaggedServicesInterface) {
                    throw new RuntimeException(sprintf(
                        'Dashboard widget resolution requires %s.',
                        TaggedServicesInterface::class,
                    ));
                }

                $providers = static function () use ($container): \Generator {
                    foreach ($container->tagged(DashboardWidgetProviderInterface::class) as $serviceId => $provider) {
                        if (!$provider instanceof DashboardWidgetProviderInterface) {
                            throw new RuntimeException(sprintf(
                                'Tagged service "%s" for tag "%s" must implement %s.',
                                $serviceId,
                                DashboardWidgetProviderInterface::class,
                                DashboardWidgetProviderInterface::class,
                            ));
                        }

                        yield $provider;
                    }
                };

                return new DashboardWidgetRegistry($providers());
            },
        );
        $container->singleton(DashboardWidgetAccessPolicy::class, DashboardWidgetAccessPolicy::class);
        $container->singleton(DashboardWidgetPreferenceModel::class, DashboardWidgetPreferenceModel::class);
        $container->singleton(DashboardLayoutService::class, DashboardLayoutService::class);
        $container->singleton(DashboardWidgetApiService::class, DashboardWidgetApiService::class);
        $container->scoped(DashboardController::class, DashboardController::class);
        $container->scoped(DashboardApiController::class, DashboardApiController::class);
        $container->singleton(DashboardRouteRegistrar::class, DashboardRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(DashboardRouteRegistrar::class));
    }
}
