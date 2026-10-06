<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje lokalizovanou presentation auditnich udalosti mutaci roli
 */
final class RolesAuditPresentationRegistrar
{
    /**
     * Nastavuje registry auditni presentation
     */
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    /**
     * Pripoji modul a eventy create, update, zmeny opravneni a lifecycle
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation('system.roles', 'roles', 'roles.module.name', AdminIcon::PersonGear));
        foreach (['created' => AdminIcon::PersonAdd, 'updated' => AdminIcon::PencilSquare, 'permissions_changed' => AdminIcon::ShieldLock, 'deleted' => AdminIcon::Trash3, 'restored' => AdminIcon::ArrowCounterclockwise] as $event => $icon) {
            $this->presentations->register(new AuditEventPresentation('system.roles.' . $event, 'roles.audit.events.' . $event, $icon));
        }
    }
}
