<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Deklaruje metadata, navigaci a pravo pro cteni systemoveho auditu
 */
final readonly class AuditModuleDefinition implements AdminModuleDefinitionInterface
{
    public function code(): string
    {
        return 'system.audit';
    }

    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            nameKey: 'audit.module.name',
            icon: AdminIcon::JournalText,
            navigationGroup: 'system',
            navigationOrder: 100,
            destinationRoute: 'admin.system.module.index',
            routeSegment: 'audit',
            destinationParameters: ['module' => 'audit'],
            navigationPermission: 'system.audit.view',
            superAdminOnly: false,
        );
    }

    /**
     * Deklaruje pravo vyhradni pro zobrazeni auditni historie
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            // Umoznuje zobrazit historii systemovych auditu pouze super administratorum
            new PermissionDefinition(
                code: 'system.audit.view',
                moduleCode: $this->code(),
                nameKey: 'audit.permissions.view',
                delegation: PermissionDelegation::SuperAdminOnly,
            ),
        ];
    }
}
