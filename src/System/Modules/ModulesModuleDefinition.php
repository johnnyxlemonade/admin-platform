<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminNavigationGroup;
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
            navigationGroup: AdminNavigationGroup::System,
            navigationOrder: 70,
            routeSegment: 'modules',
            navigationPermission: 'system.modules.view',
            superAdminOnly: false,
            descriptionKey: 'modules.module.description',
        );
    }

    /**
     * Deklaruje pravo pro zobrazeni Composer prehledu
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            new PermissionDefinition(
                code: 'system.modules.view',
                moduleCode: $this->code(),
                nameKey: 'modules.permissions.view',
                delegation: PermissionDelegation::SuperAdminOnly,
            ),
        ];
    }

    /**
     * Read-only prehled neemituje vlastni auditni udalosti
     *
     * @return list<AuditEventDefinition>
     */
    public function auditEventDefinitions(): array
    {
        return [];
    }
}
