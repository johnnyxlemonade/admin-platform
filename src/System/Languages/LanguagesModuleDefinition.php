<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages;

use Lemonade\Admin\Audit\AuditEventDefinition;
use Lemonade\Admin\Authorization\PermissionDefinition;
use Lemonade\Admin\Authorization\PermissionDelegation;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminNavigationGroup;
use Lemonade\Admin\Module\Contract\AdminModuleDefinitionInterface;

/**
 * Deklaruje metadata, navigaci a samostatna prava spravy systemovych jazyku
 */
final readonly class LanguagesModuleDefinition implements AdminModuleDefinitionInterface
{
    public function code(): string
    {
        return 'system.languages';
    }

    public function adminMetadata(): AdminModuleMetadata
    {
        return new AdminModuleMetadata(
            nameKey: 'languages.module.name',
            icon: AdminIcon::Sliders,
            navigationGroup: AdminNavigationGroup::System,
            navigationOrder: 55,
            routeSegment: 'languages',
            navigationPermission: 'system.languages.view',
            descriptionKey: 'languages.module.description',
        );
    }

    /**
     * Deklaruje prava pro cteni, editor a samostatne stavove akce
     *
     * @return list<PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            // Umoznuje zobrazit spravu systemovych jazyku
            new PermissionDefinition(
                code: 'system.languages.view',
                moduleCode: $this->code(),
                nameKey: 'languages.permissions.view',
            ),
            // Umoznuje vytvorit novy systemovy jazyk
            new PermissionDefinition(
                code: 'system.languages.create',
                moduleCode: $this->code(),
                nameKey: 'languages.permissions.create',
                delegation: PermissionDelegation::Normally,
                requires: ['system.languages.view'],
            ),
            // Umoznuje upravit metadata existujiciho systemoveho jazyka
            new PermissionDefinition(
                code: 'system.languages.edit',
                moduleCode: $this->code(),
                nameKey: 'languages.permissions.edit',
                delegation: PermissionDelegation::Normally,
                requires: ['system.languages.view'],
            ),
            // Umoznuje aktivovat systemovy jazyk
            new PermissionDefinition(
                code: 'system.languages.enable',
                moduleCode: $this->code(),
                nameKey: 'languages.permissions.enable',
                delegation: PermissionDelegation::Normally,
                requires: ['system.languages.view'],
            ),
            // Umoznuje deaktivovat systemovy jazyk mimo aktualni vychozi jazyk
            new PermissionDefinition(
                code: 'system.languages.disable',
                moduleCode: $this->code(),
                nameKey: 'languages.permissions.disable',
                delegation: PermissionDelegation::Normally,
                requires: ['system.languages.view'],
            ),
            // Umoznuje nastavit aktivni jazyk jako vychozi
            new PermissionDefinition(
                code: 'system.languages.set_default',
                moduleCode: $this->code(),
                nameKey: 'languages.permissions.set_default',
                delegation: PermissionDelegation::Normally,
                requires: ['system.languages.view'],
            ),
        ];
    }

    /**
     * Deklaruje presentation udalosti skutecnych mutaci systemovych jazyku
     *
     * @return list<AuditEventDefinition>
     */
    public function auditEventDefinitions(): array
    {
        return [
            new AuditEventDefinition('created', 'languages.audit.events.created', AdminIcon::PlusLg),
            new AuditEventDefinition('updated', 'languages.audit.events.updated', AdminIcon::PencilSquare),
            new AuditEventDefinition('enabled', 'languages.audit.events.enabled', AdminIcon::CheckCircle),
            new AuditEventDefinition('disabled', 'languages.audit.events.disabled', AdminIcon::PauseCircle),
            new AuditEventDefinition('default_changed', 'languages.audit.events.default_changed', AdminIcon::Check2),
        ];
    }
}
