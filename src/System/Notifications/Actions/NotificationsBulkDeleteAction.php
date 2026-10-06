<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje soft delete vybranych management oznameni
 */
final class NotificationsBulkDeleteAction extends NotificationBulkAction
{
    /**
     * Predava vyber lifecycle operaci soft delete
     *
     * @param list<int> $ids
     */
    protected function perform(array $ids): void
    {
        $this->lifecycle()->deleteBatch($ids);
    }

    /**
     * Urci vysledek hromadneho soft delete
     */
    protected function messageKey(): string
    {
        return 'notifications.actions.bulk_deleted';
    }
}
