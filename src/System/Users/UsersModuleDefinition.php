<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Deklaruje metadata a oddelena prava spravy uzivatelu, role assignmentu a overrides
 */
final readonly class UsersModuleDefinition implements AdminModuleDefinitionInterface
{
    public function code(): string
    {
        return 'system.users';
    }

    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            nameKey: 'users.module.name',
            icon: AdminIcon::People,
            navigationGroup: 'system',
            navigationOrder: 45,
            destinationRoute: 'admin.system.module.index',
            routeSegment: 'users',
            destinationParameters: ['module' => 'users'],
            navigationPermission: 'system.users.view',
        );
    }

    /**
     * Deklaruje prava Users capability, kde manage_roles ridi assignment a manage_permissions overrides
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            // Umoznuje zobrazit spravu administracnich uzivatelu
            new PermissionDefinition(
                code: 'system.users.view',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.view',
            ),
            // Umoznuje vytvorit noveho administracniho uzivatele
            new PermissionDefinition(
                code: 'system.users.create',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.create',
                delegation: PermissionDelegation::Normally,
                requires: ['system.users.view'],
            ),
            // Umoznuje upravit existujiciho administracniho uzivatele
            new PermissionDefinition(
                code: 'system.users.edit',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.edit',
                delegation: PermissionDelegation::Normally,
                requires: ['system.users.view'],
            ),
            // Umoznuje deaktivovat aktivniho administracniho uzivatele
            new PermissionDefinition(
                code: 'system.users.disable',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.disable',
                delegation: PermissionDelegation::Normally,
                requires: ['system.users.view'],
            ),
            // Umoznuje presunout administracniho uzivatele do kosu
            new PermissionDefinition(
                code: 'system.users.delete',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.delete',
                delegation: PermissionDelegation::Normally,
                requires: ['system.users.view'],
            ),
            // Umoznuje obnovit administracniho uzivatele z kosu
            new PermissionDefinition(
                code: 'system.users.restore',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.restore',
                delegation: PermissionDelegation::Normally,
                requires: ['system.users.view'],
            ),
            // Umoznuje menit prirazene administracni role uzivatele
            new PermissionDefinition(
                code: 'system.users.manage_roles',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.manage_roles',
                delegation: PermissionDelegation::Normally,
                requires: ['system.users.view'],
            ),
            // Umoznuje menit individualni permission overrides uzivatele
            new PermissionDefinition(
                code: 'system.users.manage_permissions',
                moduleCode: $this->code(),
                nameKey: 'users.permissions.manage_permissions',
                delegation: PermissionDelegation::Normally,
                requires: ['system.users.view'],
            ),
        ];
    }
}
