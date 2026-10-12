<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\Routing;

use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Admin\System\Media\Http\Controller\MediaDownloadController;
use Lemonade\Admin\System\Media\Http\Controller\MediaFilePresentationController;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Security\Csrf\CsrfMiddleware;

/**
 * Registruje authenticated download puvodnich souboru z Media katalogu
 */
final class MediaRouteRegistrar implements AdminRouteRegistrarInterface
{
    /**
     * Vraci stabilni identifikator rout Media capability
     */
    public function id(): string
    {
        return 'system.media';
    }

    /**
     * Vraci prioritu pred obecnym module transportem
     */
    public function priority(): int
    {
        return 310;
    }

    /**
     * Pridava autentizovany endpoint pro privatni original souboru
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.media.download',
            path: '/system/media/{file}/download',
            action: ControllerAction::for(
                controllerClass: MediaDownloadController::class,
                method: 'download',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
        $router->getNamed(
            name: 'admin.media.presentation.modal',
            path: '/system/media/{file}/presentation',
            action: ControllerAction::for(
                controllerClass: MediaFilePresentationController::class,
                method: 'modal',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
        $router->postNamed(
            name: 'admin.media.presentation.save',
            path: '/system/media/{file}/presentation',
            action: ControllerAction::for(
                controllerClass: MediaFilePresentationController::class,
                method: 'save',
            ),
        )->middleware(AdminAuthenticationMiddleware::class, CsrfMiddleware::class);
    }
}
