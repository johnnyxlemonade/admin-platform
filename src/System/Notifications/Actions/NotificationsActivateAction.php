<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje aktivaci management oznameni canonical lifecycle service
 */
final class NotificationsActivateAction extends NotificationLifecycleAction
{
    protected function mutate(int $id): void
    {
        $this->lifecycle()->activate($id);
    }

    protected function messageKey(): string
    {
        return 'notifications.actions.activated';
    }
}
