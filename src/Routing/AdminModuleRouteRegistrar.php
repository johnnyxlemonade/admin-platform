<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use Lemonade\Admin\Action\Http\Controller\ModuleActionController;
use Lemonade\Admin\Http\Controller\DataGridController;
use Lemonade\Admin\Http\Controller\ModuleController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje obecne routy pro prenos pozadavku admin modulu
 */
final readonly class AdminModuleRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Nastavuje registry canonical management rout modulu
     */
    public function __construct(private readonly AdminModuleRegistry $modules) {}

    /**
     * Vrati stabilni identifikator registrace rout
     */
    public function id(): string
    {
        return 'admin.module';
    }

    /**
     * Vrati prioritu sdilenych rout modulu
     */
    public function priority(): int
    {
        return 900;
    }

    /**
     * Zaregistruje chranene routy pro stranky, editor a akce modulu
     */
    public function registerRoutes(Router $router): void
    {
        foreach (AdminNavigationGroup::cases() as $group) {
            $this->registerManagementRoutes($router, $group);
        }
        $router->getNamed(
            name: 'admin.api.datagrid.index',
            path: '/api/datagrid/{module}',
            action: ControllerAction::for(
                controllerClass: DataGridController::class,
                method: 'index',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
    }

    /**
     * Pridava standardni lifecycle routy pro jednu canonical navigation group
     */
    private function registerManagementRoutes(Router $router, AdminNavigationGroup $group): void
    {
        $segments = $this->segmentsForGroup($group);
        $prefix = '/' . $group->value . '/{module}';
        $routePrefix = 'admin.' . $group->value . '.module.';
        $router->getNamed(
            name: $routePrefix . 'index',
            path: $prefix,
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'index',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->getNamed(
            name: $routePrefix . 'create',
            path: $prefix . '/create',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'create',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'store',
            path: $prefix . '/create',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'store',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->getNamed(
            name: $routePrefix . 'edit',
            path: $prefix . '/edit/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'edit',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'update',
            path: $prefix . '/edit/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'update',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'ajax.create',
            path: $prefix . '/ajax',
            action: ControllerAction::for(
                controllerClass: ModuleActionController::class,
                method: 'create',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'ajax.entity',
            path: $prefix . '/ajax/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleActionController::class,
                method: 'entity',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }

    /**
     * Vraci registrovane segmenty pro jednu canonical navigation group
     *
     * @return list<string>
     */
    private function segmentsForGroup(AdminNavigationGroup $group): array
    {
        $segments = [];
        foreach ($this->modules->all() as $definition) {
            if ($definition->adminMetadata()->navigationGroup === $group) {
                $segments[] = $definition->adminMetadata()->routeSegment();
            }
        }

        return $segments;
    }
}
