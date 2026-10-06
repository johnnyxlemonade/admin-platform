<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje deaktivaci vybranych management oznameni
 */
final class NotificationsBulkDeactivateAction extends NotificationBulkAction
{
    /**
     * Predava vyber lifecycle operaci deaktivace
     *
     * @param list<int> $ids
     */
    protected function perform(array $ids): void
    {
        $this->lifecycle()->deactivateBatch($ids);
    }

    /**
     * Urci vysledek hromadne deaktivace
     */
    protected function messageKey(): string
    {
        return 'notifications.actions.bulk_deactivated';
    }
}
