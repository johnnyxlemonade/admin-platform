<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation\Routing;

use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Presentation\Http\Controller\AdminFileUploadController;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje shared mutacni endpointy image upload komponenty
 */
final class AdminFileUploadRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vraci stabilni identifikator shared image upload rout
     */
    public function id(): string
    {
        return 'admin.file-upload';
    }

    /**
     * Vraci prioritu pred obecnym module transportem
     */
    public function priority(): int
    {
        return 850;
    }

    /**
     * Pridava autentizovane a CSRF chranene mutace image originalu
     */
    public function registerRoutes(Router $router): void
    {
        $router->postNamed(
            name: 'admin.file.chunk.start',
            path: '/files/{module}/{usage}/{entity}/chunks',
            action: ControllerAction::for(AdminFileUploadController::class, 'start'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.file.chunk.append',
            path: '/files/chunks/{uploadId}',
            action: ControllerAction::for(AdminFileUploadController::class, 'append'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.file.chunk.complete',
            path: '/files/chunks/{uploadId}/complete',
            action: ControllerAction::for(AdminFileUploadController::class, 'complete'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.file.chunk.abort',
            path: '/files/chunks/{uploadId}/abort',
            action: ControllerAction::for(AdminFileUploadController::class, 'abort'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.file.collection.remove',
            path: '/files/{module}/{usage}/{entity}/{file}/remove',
            action: ControllerAction::for(AdminFileUploadController::class, 'removeCollectionFile'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->postNamed(
            name: 'admin.file.collection.reorder',
            path: '/files/{module}/{usage}/{entity}/reorder',
            action: ControllerAction::for(AdminFileUploadController::class, 'reorder'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
        $router->getNamed(
            name: 'admin.file.collection.rename.modal',
            path: '/files/{module}/{usage}/{entity}/{file}/rename',
            action: ControllerAction::for(AdminFileUploadController::class, 'renameModal'),
        )->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: 'admin.file.collection.rename',
            path: '/files/{module}/{usage}/{entity}/{file}/rename',
            action: ControllerAction::for(AdminFileUploadController::class, 'rename'),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }
}
