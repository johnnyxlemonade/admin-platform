<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje lokalizovanou prezentaci auditu uzivatelskych mutaci
 */
final class UsersAuditPresentationRegistrar
{
    /**
     * Nastavi registry auditnich modulu a udalosti
     */
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    /**
     * Priradi Users udalostem modulovou prezentaci a canonical ikony
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation(
            'system.users',
            'users',
            'users.module.name',
            AdminIcon::People,
        ));
        foreach ([
            'created' => AdminIcon::PersonAdd,
            'updated' => AdminIcon::PencilSquare,
            'password_changed' => AdminIcon::Key,
            'role_changed' => AdminIcon::PersonGear,
            'permissions_changed' => AdminIcon::ShieldLock,
            'activated' => AdminIcon::CheckCircle,
            'deactivated' => AdminIcon::PauseCircle,
            'deleted' => AdminIcon::Trash3,
            'restored' => AdminIcon::ArrowCounterclockwise,
        ] as $event => $icon) {
            $this->presentations->register(new AuditEventPresentation(
                'system.users.' . $event,
                'users.audit.events.' . $event,
                $icon,
            ));
        }
    }
}
