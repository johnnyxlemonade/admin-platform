<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje lokalizovane auditni udalosti a ikony mutaci jazyku
 */
final class LanguagesAuditPresentationRegistrar
{
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    /**
     * Pridava modulovou prezentaci a presentation vsech udalosti jazyku
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation('system.languages', 'languages', 'languages.module.name', AdminIcon::Sliders));
        foreach (['created' => AdminIcon::PlusLg, 'updated' => AdminIcon::PencilSquare, 'enabled' => AdminIcon::CheckCircle, 'disabled' => AdminIcon::PauseCircle, 'default_changed' => AdminIcon::Check2] as $event => $icon) {
            $this->presentations->register(new AuditEventPresentation('system.languages.' . $event, 'languages.audit.events.' . $event, $icon));
        }
    }
}
