<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Admin\Action\Http\Controller\ModuleActionController;
use Lemonade\Admin\Action\ModuleActionRegistry;
use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\Http\Controller\DataGridController;
use Lemonade\Admin\Http\Controller\ModuleController;
use Lemonade\Admin\Page\ModulePageRegistry;
use Lemonade\Admin\Routing\AdminModuleRouteRegistrar;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje sdileny HTTP transport a registry, do nichz prispivaji admin moduly
 */
final readonly class AdminModuleTransportServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Urcuje sdilenou infrastrukturu potrebnou pro transport admin modulu
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [AdminServiceProvider::class];
    }

    /**
     * Zapisuje DataGrid, action a page registry se sdilenymi controllery modulu
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(DataGridRegistry::class, DataGridRegistry::class);
        $container->singleton(ModuleActionRegistry::class, ModuleActionRegistry::class);
        $container->singleton(ModulePageRegistry::class, ModulePageRegistry::class);
        $container->scoped(ModuleController::class, ModuleController::class);
        $container->scoped(DataGridController::class, DataGridController::class);
        $container->scoped(ModuleActionController::class, ModuleActionController::class);
    }

    /**
     * Registruje sdilenou module transport routu po container freeze
     */
    public function boot(ContainerInterface $container): void
    {
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(AdminModuleRouteRegistrar::class));
    }
}
