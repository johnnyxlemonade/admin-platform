<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final class EditorLockRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Zpracovava hodnotu id v konfiguraci editoru
     */
    public function id(): string
    {
        return 'admin.api.editor';
    }

    /**
     * Zpracovava hodnotu priority v konfiguraci editoru
     */
    public function priority(): int
    {
        return 400;
    }

    /**
     * Zpracovava hodnotu registerroutes v konfiguraci editoru
     */
    public function registerRoutes(Router $router): void
    {
        $router->postNamed(
            name: 'admin.api.editor.cleanup',
            path: '/api/editor/cleanup',
            action: ControllerAction::for(
                controllerClass: EditorLockController::class,
                method: 'cleanup',
            ),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);

        $router->postNamed(
            name: 'admin.api.editor.release',
            path: '/api/editor/{module}/{id}/release',
            action: ControllerAction::for(
                controllerClass: EditorLockController::class,
                method: 'release',
            ),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }
}
