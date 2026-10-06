<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Routing;

use Lemonade\Admin\Editor\Http\Controller\EditorModalController;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;

/**
 * Registruje sdilene endpointy modalnich editoru administrace
 */
final class EditorModalRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vraci stabilni identifikator shared modalniho transportu
     */
    public function id(): string
    {
        return 'admin.editor.modal';
    }

    /**
     * Urcuje registraci pred obecnymi management routami
     */
    public function priority(): int
    {
        return 850;
    }

    /**
     * Pridava autentizovane create a edit endpointy podle route segmentu modulu
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.api.modal.create',
            path: '/api/modal/{module}/create',
            action: ControllerAction::for(
                controllerClass: EditorModalController::class,
                method: 'create',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);

        $router->getNamed(
            name: 'admin.api.modal.edit',
            path: '/api/modal/{module}/{id}/edit',
            action: ControllerAction::for(
                controllerClass: EditorModalController::class,
                method: 'edit',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
    }
}
