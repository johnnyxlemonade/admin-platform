<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\Routing;

use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRouteRegistrarInterface;
use Lemonade\Admin\System\Modules\Http\Controller\ModulesFeaturesController;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;

/**
 * Registruje management routu feature stranky modulu
 */
final class ModulesRouteRegistrar implements AdminRouteRegistrarInterface
{
    public function id(): string
    {
        return 'system.modules';
    }

    public function priority(): int
    {
        return 850;
    }

    /**
     * Pridava autentizovany endpoint feature management stranky
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'admin.modules.features',
            path: '/system/modules/{module}/features',
            action: ControllerAction::for(
                controllerClass: ModulesFeaturesController::class,
                method: 'show',
            ),
        )->middleware(AdminAuthenticationMiddleware::class);
    }
}
