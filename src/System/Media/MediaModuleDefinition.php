<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Definuje centralni management katalogu sdilenych system_file zaznamu
 */
final readonly class MediaModuleDefinition implements AdminModuleDefinitionInterface
{
    public function code(): string
    {
        return 'system.media';
    }

    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(nameKey: 'media.module.name', icon: AdminIcon::Grid1x2, navigationGroup: AdminNavigationGroup::System, navigationOrder: 35, routeSegment: 'media', navigationPermission: 'system.media.view', descriptionKey: 'media.module.description');
    }

    /**
     * Deklaruje prava pro cteni katalogu a upravu presentation metadat souboru
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            new PermissionDefinition(code: 'system.media.view', moduleCode: $this->code(), nameKey: 'media.permissions.view'),
            new PermissionDefinition(
                code: 'system.media.edit',
                moduleCode: $this->code(),
                nameKey: 'media.permissions.edit',
                requires: ['system.media.view'],
            ),
        ];
    }

    /**
     * Shared file eventy patri skutecnemu ownerovi souboru, ne Media katalogu
     *
     * @return list<AuditEventDefinition>
     */
    public function auditEventDefinitions(): array
    {
        return [];
    }
}
