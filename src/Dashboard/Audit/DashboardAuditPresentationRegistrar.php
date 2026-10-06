<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje auditni podobu dashboardu
 */
final class DashboardAuditPresentationRegistrar
{
    /**
     * Nastavi registry zobrazeni auditnich udalosti
     */
    public function __construct(
        private readonly AuditEventPresentationRegistry $presentations,
    ) {}

    /**
     * Zaregistruje zobrazeni dashboardu a jeho auditnich udalosti
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation(
            'system.dashboard',
            'admin',
            'admin.dashboard.module.name',
            AdminIcon::Grid1x2,
        ));
        foreach (['preference_pinned', 'preference_unpinned', 'preference_position_changed', 'preference_size_changed', 'preferences_reordered'] as $event) {
            $this->presentations->register(new AuditEventPresentation('system.dashboard.' . $event, 'admin.dashboard_audit.' . $event, AdminIcon::Grid1x2));
        }
    }
}
