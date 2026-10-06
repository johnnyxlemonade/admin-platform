<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
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
        return new AdminModuleMetadata(nameKey: 'media.module.name', icon: AdminIcon::Grid1x2, navigationGroup: 'system', navigationOrder: 35, destinationRoute: 'admin.system.module.index', routeSegment: 'media', destinationParameters: ['module' => 'media'], navigationPermission: 'system.media.view');
    }

    /**
     * Deklaruje pravo pro zobrazeni sdileneho katalogu
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            new PermissionDefinition(code: 'system.media.view', moduleCode: $this->code(), nameKey: 'media.permissions.view'),
        ];
    }
}
