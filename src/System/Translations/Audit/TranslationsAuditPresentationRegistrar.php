<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\Audit;

use Lemonade\Admin\Audit\AuditEventPresentation;
use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Audit\AuditModulePresentation;
use Lemonade\Admin\Icon\AdminIcon;

/**
 * Registruje lokalizovanou prezentaci auditu zmen vlastnich prekladu
 */
final class TranslationsAuditPresentationRegistrar
{
    /**
     * Nastavuje registry presentation auditnich udalosti
     */
    public function __construct(private readonly AuditEventPresentationRegistry $presentations) {}

    /**
     * Prirazuje modulu a jeho override udalostem lokalizovane nazvy a ikony
     */
    public function register(): void
    {
        $this->presentations->registerModule(new AuditModulePresentation(
            'system.translations',
            'translations',
            'translations.module.name',
            AdminIcon::JournalText,
        ));
        foreach ([
            'override_created' => AdminIcon::PlusLg,
            'override_updated' => AdminIcon::PencilSquare,
            'override_removed' => AdminIcon::ArrowCounterclockwise,
        ] as $event => $icon) {
            $this->presentations->register(new AuditEventPresentation(
                'system.translations.' . $event,
                'translations.audit.events.' . substr($event, strlen('override_')),
                $icon,
            ));
        }
    }
}
