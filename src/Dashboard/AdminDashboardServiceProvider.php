<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Dashboard\Http\Controller\DashboardApiController;
use Lemonade\Admin\Dashboard\Http\Controller\DashboardController;
use Lemonade\Admin\Dashboard\Migrations\CreateDashboardWidgets;
use Lemonade\Admin\Dashboard\Routing\DashboardRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Migration\MigrationRegistry;

/**
 * Registruje dashboardovou capability a jeji tagged widget composition
 */
final readonly class AdminDashboardServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
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
        $container->singleton(DashboardWidgetRegistry::class, DashboardWidgetRegistry::class);
        $container->scoped(DashboardWidgetResolver::class, DashboardWidgetResolver::class);
        $container->scoped(DashboardLayoutService::class, DashboardLayoutService::class);
        $container->scoped(DashboardWidgetApiService::class, DashboardWidgetApiService::class);
        $container->scoped(DashboardController::class, DashboardController::class);
        $container->scoped(DashboardApiController::class, DashboardApiController::class);
    }

    /**
     * Registruje dashboardovou routu a schema migraci po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(DashboardRouteRegistrar::class));
        $container->get(MigrationRegistry::class)->register(CreateDashboardWidgets::class);
    }
}
