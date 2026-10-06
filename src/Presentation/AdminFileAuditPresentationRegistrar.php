<?php

declare(strict_types=1);

namespace Lemonade\Admin\Presentation;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje lokalizovanou auditni prezentaci shared file mutaci
 */
final class AdminFileAuditPresentationRegistrar
{
    /**
     * Nastavuje registry presentation auditnich udalosti
     */
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    /**
     * Priradi shared file udalostem prekladove klice a odpovidajici ikony
     */
    public function register(): void
    {
        foreach ([
            'uploaded' => AdminIcon::PlusLg,
            'replaced' => AdminIcon::PencilSquare,
            'removed' => AdminIcon::Trash3,
            'reordered' => AdminIcon::GripVertical,
            'renamed' => AdminIcon::PencilSquare,
        ] as $event => $icon) {
            $this->presentations->register(new AuditEventPresentation(
                'system.files.' . $event,
                'admin.files.audit.events.' . $event,
                $icon,
            ));
        }
    }
}
