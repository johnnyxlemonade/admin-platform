<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\Actions;

/**
 * Deleguje deaktivaci management oznameni canonical lifecycle service
 */
final class NotificationsDeactivateAction extends NotificationLifecycleAction
{
    protected function mutate(int $id): void
    {
        $this->lifecycle()->deactivate($id);
    }

    protected function messageKey(): string
    {
        return 'notifications.actions.deactivated';
    }
}
