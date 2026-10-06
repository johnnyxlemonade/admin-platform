<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Modules\State\ModuleManager;

/**
 * Ridi pristup k dashboardovemu widgetu
 */
final class DashboardWidgetAccessPolicy
{
    /**
     * Nastavi sluzby pro overeni stavu modulu a opravneni
     */
    public function __construct(
        private readonly ModuleManager $modules,
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * Urci zda muze uzivatel zobrazit widget dostupneho modulu
     */
    public function canView(DashboardWidgetDefinition $definition): bool
    {
        if (!$this->modules->installed($definition->moduleCode()) || !$this->modules->enabled($definition->moduleCode())) {
            return false;
        }
        $permission = $definition->access()->permissionCode();
        if ($permission !== null) {
            return $this->authorization->hasPermission($permission);
        }

        return true;
    }
}
