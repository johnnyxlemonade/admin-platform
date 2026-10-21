<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use Lemonade\Admin\Authorization\AuthorizationService;

/**
 * Ridi pristup k dashboardovemu widgetu
 */
final readonly class DashboardWidgetAccessPolicy
{
    /**
     * Nastavi autorizaci dashboardovych widgetu
     */
    public function __construct(
        private AuthorizationService $authorization,
    ) {}

    /**
     * Urci zda muze uzivatel zobrazit registrovany widget
     */
    public function canView(DashboardWidgetDefinition $definition): bool
    {
        $permission = $definition->access()->permissionCode();
        if ($permission !== null) {
            return $this->authorization->hasPermission($permission);
        }

        return true;
    }
}
