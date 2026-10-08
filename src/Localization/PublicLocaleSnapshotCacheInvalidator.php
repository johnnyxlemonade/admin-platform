<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventListenerInterface;

/**
 * Po commitu zrusi public snapshot pri zmene systemovych jazyku
 */
final class PublicLocaleSnapshotCacheInvalidator implements DomainEventListenerInterface
{
    /**
     * Nastavuje registry s persistentnim public locale snapshotem
     */
    public function __construct(private readonly LanguageRegistry $languages) {}

    /**
     * Invaliduje snapshot pouze pro udalosti menici public locale semantics
     */
    public function handle(DomainEvent $event): void
    {
        if (!in_array($event->code(), [
            'system.languages.created',
            'system.languages.updated',
            'system.languages.enabled',
            'system.languages.disabled',
            'system.languages.default_changed',
        ], true)) {
            return;
        }

        $this->languages->forgetPublicLocaleSnapshot();
    }
}
