<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje soft delete management oznameni canonical lifecycle service
 */
final class NotificationsDeleteAction extends NotificationLifecycleAction
{
    protected function mutate(int $id): void
    {
        $this->lifecycle()->delete($id);
    }

    protected function messageKey(): string
    {
        return 'notifications.actions.deleted';
    }
}
