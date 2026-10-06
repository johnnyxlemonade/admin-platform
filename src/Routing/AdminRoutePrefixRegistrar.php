<?php

declare(strict_types=1);

namespace Lemonade\Admin\Routing;

use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRegistrarInterface;

/**
 * Aplikuje host base path na vsechny relativni Admin routy
 */
final class AdminRoutePrefixRegistrar implements RouteRegistrarInterface
{
    /**
     * Nastavuje konfiguraci host cesty a registry Admin rout
     */
    public function __construct(
        private readonly AdminRoutingConfiguration $routing,
        private readonly AdminRouteRegistrarRegistry $registrars,
    ) {}

    /**
     * Vrati stabilni identifikator globalniho Admin transportu
     */
    public function id(): string
    {
        return 'admin.routes';
    }

    /**
     * Vrati prioritu pred public catch-all routami
     */
    public function priority(): int
    {
        return 100;
    }

    /**
     * Registruje vsechny Admin capability pod host base path
     */
    public function registerRoutes(Router $router): void
    {
        $router->group($this->routing->basePath, function (Router $router): void {
            $this->registrars->registerRoutes($router);
        });
    }
}
