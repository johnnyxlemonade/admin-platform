<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Deklaruje navigaci a management prava systemovych oznameni
 */
final readonly class NotificationsModuleDefinition implements AdminModuleDefinitionInterface
{
    public function code(): string
    {
        return 'system.notifications';
    }

    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            nameKey: 'notifications.module.name',
            icon: AdminIcon::Bell,
            navigationGroup: 'system',
            navigationOrder: 65,
            destinationRoute: 'admin.system.module.index',
            routeSegment: 'notifications',
            destinationParameters: ['module' => 'notifications'],
            navigationPermission: 'system.notifications.view',
        );
    }

    /**
     * Deklaruje prava pro management editoru, lifecycle a zobrazeni prehledu
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            // Umoznuje zobrazit prehled systemovych oznameni
            new PermissionDefinition(
                code: 'system.notifications.view',
                moduleCode: $this->code(),
                nameKey: 'notifications.permissions.view',
            ),
            // Umoznuje publikovat a upravovat systemova oznameni
            new PermissionDefinition(
                code: 'system.notifications.publish',
                moduleCode: $this->code(),
                nameKey: 'notifications.permissions.publish',
                delegation: PermissionDelegation::Normally,
                requires: ['system.notifications.view'],
            ),
            // Umoznuje aktivovat neaktivni oznameni
            new PermissionDefinition(
                code: 'system.notifications.activate',
                moduleCode: $this->code(),
                nameKey: 'notifications.permissions.activate',
                delegation: PermissionDelegation::Normally,
                requires: ['system.notifications.view'],
            ),
            // Umoznuje deaktivovat aktivni oznameni
            new PermissionDefinition(
                code: 'system.notifications.deactivate',
                moduleCode: $this->code(),
                nameKey: 'notifications.permissions.deactivate',
                delegation: PermissionDelegation::Normally,
                requires: ['system.notifications.view'],
            ),
            // Umoznuje presunout oznameni do kosu
            new PermissionDefinition(
                code: 'system.notifications.delete',
                moduleCode: $this->code(),
                nameKey: 'notifications.permissions.delete',
                delegation: PermissionDelegation::Normally,
                requires: ['system.notifications.view'],
            ),
            // Umoznuje obnovit oznameni z kosu
            new PermissionDefinition(
                code: 'system.notifications.restore',
                moduleCode: $this->code(),
                nameKey: 'notifications.permissions.restore',
                delegation: PermissionDelegation::Normally,
                requires: ['system.notifications.view'],
            ),
            // Umoznuje obnovit stav zobrazeni oznameni pro prijemce
            new PermissionDefinition(
                code: 'system.notifications.reset_display',
                moduleCode: $this->code(),
                nameKey: 'notifications.permissions.reset_display',
                delegation: PermissionDelegation::Normally,
                requires: ['system.notifications.view'],
            ),
        ];
    }
}
