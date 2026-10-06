<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor;

use Lemonade\Admin\AdminServiceProvider;
use Lemonade\Admin\Editor\Http\Controller\EditorModalController;
use Lemonade\Admin\Editor\Lock\EditorLockController;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Editor\Lock\EditorLockOwnerReleaser;
use Lemonade\Admin\Editor\Lock\EditorLockOwnerReleaserInterface;
use Lemonade\Admin\Editor\Lock\EditorLockRouteRegistrar;
use Lemonade\Admin\Editor\Lock\EditorModalLoader;
use Lemonade\Admin\Editor\Routing\EditorModalRouteRegistrar;
use Lemonade\Admin\Module\AdminModuleTransportServiceProvider;
use Lemonade\Admin\Routing\AdminRouteRegistrarRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\DependentServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;

/**
 * Registruje sdileny editor protocol, zamky a jejich HTTP endpointy
 */
final class AdminEditorServiceProvider implements ServiceProviderInterface, DependentServiceProviderInterface
{
    /**
     * Urcuje sdilenou infrastrukturu potrebnou pro editory modulu
     *
     * @return list<class-string>
     */
    public static function requires(): array
    {
        return [
            AdminServiceProvider::class,
            AdminModuleTransportServiceProvider::class,
        ];
    }

    /**
     * Zapisuje editor registry, dispatching, zamky a route registrar
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(EditorRegistry::class, EditorRegistry::class);
        $container->singleton(EditorDispatcher::class, EditorDispatcher::class);
        $container->singleton(EditorLockOwnerReleaserInterface::class, EditorLockOwnerReleaser::class);
        $container->singleton(EditorLockManager::class, EditorLockManager::class);
        $container->singleton(EditorModalLoader::class, EditorModalLoader::class);
        $container->scoped(EditorLockController::class, EditorLockController::class);
        $container->scoped(EditorModalController::class, EditorModalController::class);
        $container->singleton(EditorLockRouteRegistrar::class, EditorLockRouteRegistrar::class);
        $container->singleton(EditorModalRouteRegistrar::class, EditorModalRouteRegistrar::class);
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(EditorLockRouteRegistrar::class));
        $container->get(AdminRouteRegistrarRegistry::class)->register($container->get(EditorModalRouteRegistrar::class));
    }
}
