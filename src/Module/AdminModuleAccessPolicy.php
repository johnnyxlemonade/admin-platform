<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Rozhoduje pristup k administracnimu modulu
 */
final class AdminModuleAccessPolicy
{
    /**
     * Nastavuje zavislosti potrebne pro praci s administracnimi moduly
     */
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly CurrentPrincipalProviderInterface $principals,
    ) {}

    /**
     * Rozhoduje, zda aktualni principal muze otevrit modul
     */
    public function canAccess(AdminModuleDefinitionInterface $module): bool
    {
        if (!$module->adminMetadata()->superAdminOnly()) {
            return true;
        }

        $principal = $this->principals->currentUser();

        return $principal !== null && $this->authorization->isSuperAdmin($principal);
    }
}
