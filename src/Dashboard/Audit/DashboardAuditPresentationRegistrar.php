<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje prezentacni metadata auditnich udalosti dashboardu
 */
final readonly class DashboardAuditPresentationRegistrar
{
    /**
     * Definuje auditni udalosti dashboardu a jejich prezentaci
     *
     * @var array<string, array{translation: string, icon: AdminIcon}>
     */
    private const array EVENTS = [
        'system.dashboard.preference_pinned' => [
            'translation' => 'admin.dashboard_audit.preference_pinned',
            'icon' => AdminIcon::Grid1x2,
        ],
        'system.dashboard.preference_unpinned' => [
            'translation' => 'admin.dashboard_audit.preference_unpinned',
            'icon' => AdminIcon::Grid1x2,
        ],
        'system.dashboard.preference_position_changed' => [
            'translation' => 'admin.dashboard_audit.preference_position_changed',
            'icon' => AdminIcon::Grid1x2,
        ],
        'system.dashboard.preference_size_changed' => [
            'translation' => 'admin.dashboard_audit.preference_size_changed',
            'icon' => AdminIcon::Grid1x2,
        ],
        'system.dashboard.preferences_reordered' => [
            'translation' => 'admin.dashboard_audit.preferences_reordered',
            'icon' => AdminIcon::Grid1x2,
        ],
    ];

    /**
     * Nastavuje registry zobrazeni auditnich udalosti dashboardu
     */
    public function __construct(
        private AuditEventPresentationRegistry $presentations,
    ) {}

    /**
     * Zaregistruje modul dashboardu a vsechny jeho auditni udalosti
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation(
            'system.dashboard',
            'admin.dashboard.module.name',
            AdminIcon::Grid1x2,
        ));
        foreach (self::EVENTS as $code => $definition) {
            $this->presentations->register(new AuditEventPresentation(
                $code,
                $definition['translation'],
                $definition['icon'],
            ));
        }
    }
}
