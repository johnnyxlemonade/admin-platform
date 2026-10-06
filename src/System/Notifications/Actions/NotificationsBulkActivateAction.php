<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje aktivaci vybranych oznameni a reset jejich zobrazeni prijemcum
 */
final class NotificationsBulkActivateAction extends NotificationBulkAction
{
    /**
     * Predava vyber lifecycle operaci aktivace s resetem zobrazeni
     *
     * @param list<int> $ids
     */
    protected function perform(array $ids): void
    {
        $this->lifecycle()->activateAndResetDisplayBatch($ids);
    }

    /**
     * Urci vysledek hromadne aktivace
     */
    protected function messageKey(): string
    {
        return 'notifications.actions.bulk_activated';
    }
}
