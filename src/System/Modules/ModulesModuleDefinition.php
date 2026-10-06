<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Deklaruje navigaci a management prava nad sdilenym module runtime
 */
final readonly class ModulesModuleDefinition implements AdminModuleDefinitionInterface
{
    public function code(): string
    {
        return 'system.modules';
    }

    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            nameKey: 'modules.module.name',
            icon: AdminIcon::Boxes,
            navigationGroup: 'system',
            navigationOrder: 70,
            destinationRoute: 'admin.system.module.index',
            routeSegment: 'modules',
            destinationParameters: ['module' => 'modules'],
            navigationPermission: 'system.modules.view',
            superAdminOnly: false,
        );
    }

    /**
     * Deklaruje oddelena management prava pro cteni, lifecycle akce a feature akce
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            // Umoznuje zobrazit spravu instalovanych modulu
            new PermissionDefinition(
                code: 'system.modules.view',
                moduleCode: $this->code(),
                nameKey: 'modules.permissions.view',
                delegation: PermissionDelegation::SuperAdminOnly,
            ),
            // Umoznuje nainstalovat dostupny modul
            new PermissionDefinition(
                code: 'system.modules.install',
                moduleCode: $this->code(),
                nameKey: 'modules.permissions.install',
                delegation: PermissionDelegation::SuperAdminOnly,
                requires: ['system.modules.view'],
            ),
            // Umoznuje zapnout nainstalovany modul
            new PermissionDefinition(
                code: 'system.modules.enable',
                moduleCode: $this->code(),
                nameKey: 'modules.permissions.enable',
                delegation: PermissionDelegation::SuperAdminOnly,
                requires: ['system.modules.view'],
            ),
            // Umoznuje vypnout aktivni modul
            new PermissionDefinition(
                code: 'system.modules.disable',
                moduleCode: $this->code(),
                nameKey: 'modules.permissions.disable',
                delegation: PermissionDelegation::SuperAdminOnly,
                requires: ['system.modules.view'],
            ),
            // Umoznuje menit nastavitelne feature modulu
            new PermissionDefinition(
                code: 'system.modules.manage_features',
                moduleCode: $this->code(),
                nameKey: 'modules.permissions.manage_features',
                delegation: PermissionDelegation::SuperAdminOnly,
                requires: ['system.modules.view'],
            ),
        ];
    }
}
