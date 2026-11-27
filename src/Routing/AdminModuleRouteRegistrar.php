<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use Lemonade\Admin\Action\Http\Controller\ModuleActionController;
use Lemonade\Admin\Http\Controller\DataGridController;
use Lemonade\Admin\Http\Controller\ModuleController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Module\AdminModuleRegistry;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Exception\AdminModuleNotFoundException;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;
use Psr\Http\Message\ServerRequestInterface;

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
        $prefix = '/' . $group->value . '/{module}';
        $routePrefix = 'admin.' . $group->value . '.module.';
        $router->getNamed(
            name: $routePrefix . 'index',
            path: $prefix,
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'index',
            ),
        )->constrainParameterUsing('module', fn(string $segment, ?ServerRequestInterface $request): bool => $this->belongsToGroup($segment, $group, $request))->middleware(AdminAuthenticationMiddleware::class);
        $router->getNamed(
            name: $routePrefix . 'create',
            path: $prefix . '/create',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'create',
            ),
        )->constrainParameterUsing('module', fn(string $segment, ?ServerRequestInterface $request): bool => $this->belongsToGroup($segment, $group, $request))->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'store',
            path: $prefix . '/create',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'store',
            ),
        )->constrainParameterUsing('module', fn(string $segment, ?ServerRequestInterface $request): bool => $this->belongsToGroup($segment, $group, $request))->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->getNamed(
            name: $routePrefix . 'edit',
            path: $prefix . '/edit/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'edit',
            ),
        )->constrainParameterUsing('module', fn(string $segment, ?ServerRequestInterface $request): bool => $this->belongsToGroup($segment, $group, $request))->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'update',
            path: $prefix . '/edit/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleController::class,
                method: 'update',
            ),
        )->constrainParameterUsing('module', fn(string $segment, ?ServerRequestInterface $request): bool => $this->belongsToGroup($segment, $group, $request))->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'ajax.create',
            path: $prefix . '/ajax',
            action: ControllerAction::for(
                controllerClass: ModuleActionController::class,
                method: 'create',
            ),
        )->constrainParameterUsing('module', fn(string $segment, ?ServerRequestInterface $request): bool => $this->belongsToGroup($segment, $group, $request))->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: $routePrefix . 'ajax.entity',
            path: $prefix . '/ajax/{id}',
            action: ControllerAction::for(
                controllerClass: ModuleActionController::class,
                method: 'entity',
            ),
        )->constrainParameterUsing('module', fn(string $segment, ?ServerRequestInterface $request): bool => $this->belongsToGroup($segment, $group, $request))->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }

    /**
     * Overi ze lazy registrovany modul patri do skupiny odpovidajici route
     */
    private function belongsToGroup(string $segment, AdminNavigationGroup $group, ?ServerRequestInterface $request): bool
    {
        $modules = $request?->getAttribute(AdminModuleRegistry::class) ?? $this->modules;
        if (!$modules instanceof AdminModuleRegistry) {
            throw new \LogicException('Invalid request module registry.');
        }
        try {
            $code = $modules->moduleCodeByRouteSegment($segment);
        } catch (AdminModuleNotFoundException) {
            return false;
        }

        return $modules->definition($code)->adminMetadata()->navigationGroup() === $group;
    }
}
