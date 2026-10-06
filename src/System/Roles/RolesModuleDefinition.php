<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Definuje metadata modulu roli a management permissions oddelene od prirazovani roli uzivatelum
 */
final readonly class RolesModuleDefinition implements AdminModuleDefinitionInterface
{
    public function code(): string
    {
        return 'system.roles';
    }

    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            nameKey: 'roles.module.name',
            icon: AdminIcon::ShieldLock,
            navigationGroup: 'system',
            navigationOrder: 50,
            destinationRoute: 'admin.system.module.index',
            routeSegment: 'roles',
            destinationParameters: ['module' => 'roles'],
            navigationPermission: 'system.roles.view',
        );
    }

    /**
     * Deklaruje view, create, edit, delete a restore permissions zavisle na view
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            // Umoznuje zobrazit spravu administracnich roli
            new PermissionDefinition(
                code: 'system.roles.view',
                moduleCode: $this->code(),
                nameKey: 'roles.permissions.view',
            ),
            // Umoznuje vytvorit novou administracni roli
            new PermissionDefinition(
                code: 'system.roles.create',
                moduleCode: $this->code(),
                nameKey: 'roles.permissions.create',
                delegation: PermissionDelegation::Normally,
                requires: ['system.roles.view'],
            ),
            // Umoznuje upravit existujici administracni roli
            new PermissionDefinition(
                code: 'system.roles.edit',
                moduleCode: $this->code(),
                nameKey: 'roles.permissions.edit',
                delegation: PermissionDelegation::Normally,
                requires: ['system.roles.view'],
            ),
            // Umoznuje presunout administracni roli do kosu
            new PermissionDefinition(
                code: 'system.roles.delete',
                moduleCode: $this->code(),
                nameKey: 'roles.permissions.delete',
                delegation: PermissionDelegation::Normally,
                requires: ['system.roles.view'],
            ),
            // Umoznuje obnovit administracni roli z kosu
            new PermissionDefinition(
                code: 'system.roles.restore',
                moduleCode: $this->code(),
                nameKey: 'roles.permissions.restore',
                delegation: PermissionDelegation::Normally,
                requires: ['system.roles.view'],
            ),
        ];
    }
}
