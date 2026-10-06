<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje reset zobrazeni management oznameni pro jeho prijemce
 */
final class NotificationsResetDisplayAction extends NotificationLifecycleAction
{
    protected function mutate(int $id): void
    {
        $this->lifecycle()->resetDisplay($id);
    }

    protected function messageKey(): string
    {
        return 'notifications.actions.display_reset';
    }
}
