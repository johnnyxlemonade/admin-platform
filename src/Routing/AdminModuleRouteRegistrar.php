<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use Lemonade\Admin\Action\Http\Controller\ModuleActionController;
use Lemonade\Admin\Http\Controller\DataGridController;
use Lemonade\Admin\Http\Controller\ModuleController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje obecne routy pro prenos pozadavku admin modulu
 */
final class AdminModuleRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Nastavuje registry pro rozliseni System a ostatnich management rout
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
        $this->registerSystemRoutes($router);
        $this->registerModuleRoutes($router);
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
     * Pridava canonical routy management modulu vlastnenych System namespace
     */
    private function registerSystemRoutes(Router $router): void
    {
        $segments = $this->systemSegments();
        $router->getNamed(
            name: 'admin.system.module.index',
            path: '/system/{module}',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'index',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->getNamed(
            name: 'admin.system.module.create',
            path: '/system/{module}/create',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'create',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: 'admin.system.module.store',
            path: '/system/{module}/create',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'store',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->getNamed(
            name: 'admin.system.module.edit',
            path: '/system/{module}/edit/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'edit',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: 'admin.system.module.update',
            path: '/system/{module}/edit/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'update',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.system.module.ajax.create',
            path: '/system/{module}/ajax',
            action: ControllerAction::for(
                controllerClass: ModuleActionController::class,
                method: 'create',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.system.module.ajax.entity',
            path: '/system/{module}/ajax/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleActionController::class,
                method: 'entity',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }

    /**
     * Pridava routy pro administracni moduly mimo System namespace
     */
    private function registerModuleRoutes(Router $router): void
    {
        $segments = $this->nonSystemSegments();
        $router->getNamed(
            name: 'admin.module.index',
            path: '/{module}',
            action: ControllerAction::for(controllerClass: ModuleController::class, method: 'index'),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->getNamed(
            name: 'admin.module.create',
            path: '/{module}/create',
            action: ControllerAction::for(controllerClass: ModuleController::class, method: 'create'),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: 'admin.module.store',
            path: '/{module}/create',
            action: ControllerAction::for(controllerClass: ModuleController::class, method: 'store'),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->getNamed(
            name: 'admin.module.edit',
            path: '/{module}/edit/{id}',
            action: ControllerAction::for(controllerClass: ModuleController::class, method: 'edit'),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: 'admin.module.update',
            path: '/{module}/edit/{id}',
            action: ControllerAction::for(controllerClass: ModuleController::class, method: 'update'),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.module.ajax.create',
            path: '/{module}/ajax',
            action: ControllerAction::for(controllerClass: ModuleActionController::class, method: 'create'),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.module.ajax.entity',
            path: '/{module}/ajax/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleActionController::class,
                method: 'entity',
            ),
        )->constrainParameter('module', $segments)->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }

    /**
     * Vraci segmenty modulu vlastnenych System namespace
     *
     * @return list<string>
     */
    private function systemSegments(): array
    {
        return $this->segmentsFor(static fn(string $code): bool => str_starts_with($code, 'system.'));
    }

    /**
     * Vraci segmenty modulu mimo System namespace
     *
     * @return list<string>
     */
    private function nonSystemSegments(): array
    {
        return $this->segmentsFor(static fn(string $code): bool => !str_starts_with($code, 'system.'));
    }

    /**
     * Filtruje registrovane segmenty podle ownershipu modulu
     *
     * @param callable(string): bool $matches
     * @return list<string>
     */
    private function segmentsFor(callable $matches): array
    {
        $segments = [];
        foreach ($this->modules->all() as $definition) {
            if ($matches($definition->code())) {
                $segments[] = $definition->adminMetadata()->routeSegment();
            }
        }

        return $segments;
    }
}
