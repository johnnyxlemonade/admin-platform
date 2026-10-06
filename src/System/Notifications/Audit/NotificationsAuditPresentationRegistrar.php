<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje lokalizovanou auditni prezentaci management mutaci oznameni
 */
final class NotificationsAuditPresentationRegistrar
{
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    /**
     * Pridava modulovou presentation a ikony udalosti publikovani a lifecycle
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation('system.notifications', 'notifications', 'notifications.module.name', AdminIcon::Bell));
        foreach (['published' => AdminIcon::PlusLg, 'updated' => AdminIcon::PencilSquare, 'activated' => AdminIcon::CheckCircle, 'deactivated' => AdminIcon::PauseCircle, 'deleted' => AdminIcon::Trash3, 'restored' => AdminIcon::ArrowCounterclockwise, 'display_reset' => AdminIcon::ArrowCounterclockwise] as $event => $icon) {
            $this->presentations->register(new AuditEventPresentation('system.notifications.' . $event, 'notifications.audit.events.' . $event, $icon));
        }
    }
}
