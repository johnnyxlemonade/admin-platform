<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje obnoveni management oznameni canonical lifecycle service
 */
final class NotificationsRestoreAction extends NotificationLifecycleAction
{
    protected function mutate(int $id): void
    {
        $this->lifecycle()->restore($id);
    }

    protected function messageKey(): string
    {
        return 'notifications.actions.restored';
    }
}
