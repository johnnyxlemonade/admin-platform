<?php

declare(strict_types=1);

namespace Lemonade\Admin\Localization;

use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\DomainEventListenerInterface;

/**
 * Po commitu invaliduje cache zmenene translation override skupiny
 */
final class TranslationOverrideCacheInvalidator implements DomainEventListenerInterface
{
    /**
     * Nastavuje cache s hodnotami a revizemi explicitnich overrides
     */
    public function __construct(private readonly TranslationOverrideCache $cache) {}

    /**
     * Reaguje jen na uspesne zapsane zmeny explicitnich override hodnot
     */
    public function handle(DomainEvent $event): void
    {
        if (!in_array($event->code(), [
            'system.translations.override_created',
            'system.translations.override_updated',
            'system.translations.override_removed',
        ], true)) {
            return;
        }

        $payload = $event->payload();
        $locale = $payload['locale'] ?? null;
        $group = $payload['group'] ?? null;
        if (!is_string($locale) || !is_string($group)) {
            return;
        }

        $this->cache->forget($locale, $group);
    }
}
