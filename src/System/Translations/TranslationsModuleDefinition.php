<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations;

use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Deklaruje metadata navigace a prava spravy runtime prekladu
 */
final readonly class TranslationsModuleDefinition implements AdminModuleDefinitionInterface
{
    /**
     * Vraci canonical kod modulu
     */
    public function code(): string
    {
        return 'system.translations';
    }

    /**
     * Pridava modul do skupiny System se stabilnim segmentem translations
     */
    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            nameKey: 'translations.module.name',
            icon: AdminIcon::JournalText,
            navigationGroup: 'system',
            navigationOrder: 60,
            destinationRoute: 'admin.system.module.index',
            routeSegment: 'translations',
            destinationParameters: ['module' => 'translations'],
            navigationPermission: 'system.translations.view',
        );
    }

    /**
     * Deklaruje cteni a mutaci explicitnich override hodnot
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            // Umoznuje zobrazit spravu runtime prekladu
            new PermissionDefinition(
                code: 'system.translations.view',
                moduleCode: $this->code(),
                nameKey: 'translations.permissions.view',
            ),
            // Umoznuje upravit override hodnotu existujiciho prekladu
            new PermissionDefinition(
                code: 'system.translations.edit',
                moduleCode: $this->code(),
                nameKey: 'translations.permissions.edit',
                delegation: PermissionDelegation::Normally,
                requires: ['system.translations.view'],
            ),
        ];
    }
}
