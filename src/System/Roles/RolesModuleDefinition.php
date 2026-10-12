<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminNavigationGroup;
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
            navigationGroup: AdminNavigationGroup::System,
            navigationOrder: 50,
            routeSegment: 'roles',
            navigationPermission: 'system.roles.view',
            descriptionKey: 'roles.module.description',
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

    /**
     * Deklaruje presentation udalosti skutecnych mutaci roli
     *
     * @return list<AuditEventDefinition>
     */
    public function auditEventDefinitions(): array
    {
        return [
            new AuditEventDefinition('created', 'roles.audit.events.created', AdminIcon::PersonAdd),
            new AuditEventDefinition('updated', 'roles.audit.events.updated', AdminIcon::PencilSquare),
            new AuditEventDefinition('permissions_changed', 'roles.audit.events.permissions_changed', AdminIcon::ShieldLock),
            new AuditEventDefinition('deleted', 'roles.audit.events.deleted', AdminIcon::Trash3),
            new AuditEventDefinition('restored', 'roles.audit.events.restored', AdminIcon::ArrowCounterclockwise),
        ];
    }
}
