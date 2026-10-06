<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje obnoveni vybranych oznameni do neaktivniho stavu
 */
final class NotificationsBulkRestoreAction extends NotificationBulkAction
{
    /**
     * Predava vyber lifecycle operaci obnoveni do neaktivniho stavu
     *
     * @param list<int> $ids
     */
    protected function perform(array $ids): void
    {
        $this->lifecycle()->restoreAsInactiveBatch($ids);
    }

    /**
     * Urci vysledek hromadne obnovy
     */
    protected function messageKey(): string
    {
        return 'notifications.actions.bulk_restored';
    }
}
