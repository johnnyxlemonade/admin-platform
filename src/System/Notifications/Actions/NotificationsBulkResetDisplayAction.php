<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje reset zobrazeni vybranych aktivnich oznameni jejich prijemcum
 */
final class NotificationsBulkResetDisplayAction extends NotificationBulkAction
{
    /**
     * Predava vyber lifecycle operaci resetu zobrazeni
     *
     * @param list<int> $ids
     */
    protected function perform(array $ids): void
    {
        $this->lifecycle()->resetDisplayBatch($ids);
    }

    /**
     * Urci vysledek hromadneho resetu zobrazeni
     */
    protected function messageKey(): string
    {
        return 'notifications.actions.bulk_display_reset';
    }
}
