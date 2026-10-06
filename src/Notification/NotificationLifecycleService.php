<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Notification\Models\NotificationModel;
use RuntimeException;

/**
 * Zpracovava zivotni cyklus nebo doruceni administracnich oznameni
 */
final class NotificationLifecycleService
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani oznameni
     */
    public function __construct(
        private readonly NotificationModel $notifications,
        private readonly LocalActorGuard $actors,
        private readonly TransactionalEventProcessor $events,
    ) {}

    /**
     * Zpracovava hodnotu activate pro oznameni
     */
    public function activate(int $id): void
    {
        $this->setActive($id, true);
    }

    /**
     * Zpracovava hodnotu deactivate pro oznameni
     */
    public function deactivate(int $id): void
    {
        $this->setActive($id, false);
    }

    /**
     * Zpracovava hodnotu delete pro oznameni
     */
    public function delete(int $id): void
    {
        $state = $this->state($id);
        if ($state['deleted_at'] !== null) {
            return;
        }
        $actor = $this->actor();
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.delete', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $state): void {
            if (!$this->notifications->softDeleteNotification($id)) {
                throw new RuntimeException('notifications.validation.lifecycle_changed');
            }
            $events->record(new DomainEvent('system.notifications.deleted', 'system.notifications', 'notification', (string) $id, ['active' => (int) $state['active'] === 1]));
        });
    }

    /**
     * Zpracovava hodnotu restore pro oznameni
     */
    public function restore(int $id): void
    {
        $state = $this->state($id);
        if ($state['deleted_at'] === null) {
            return;
        }
        $actor = $this->actor();
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.restore', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $state): void {
            if (!$this->notifications->restoreNotification($id)) {
                throw new RuntimeException('notifications.validation.lifecycle_changed');
            }
            $events->record(new DomainEvent('system.notifications.restored', 'system.notifications', 'notification', (string) $id, ['active' => (int) $state['active'] === 1]));
        });
    }

    /**
     * Zpracovava hodnotu resetdisplay pro oznameni
     */
    public function resetDisplay(int $id): void
    {
        $state = $this->state($id);
        if ($state['deleted_at'] !== null) {
            throw new RuntimeException('notifications.validation.deleted_cannot_reset_display');
        }
        $actor = $this->actor();
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.reset_display', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id): void {
            $this->notifications->resetDisplayState($id);
            $events->record(new DomainEvent('system.notifications.display_reset', 'system.notifications', 'notification', (string) $id));
        });
    }

    /**
     * Zpracovava hodnotu deactivatebatch pro oznameni
     * @param list<int> $ids
     */
    public function deactivateBatch(array $ids): void
    {
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.deactivate', AuditActor::user($this->actor()->id())), function (TransactionalEventCollector $events) use ($ids): void {
            $this->statesForBatch($ids, static fn(array $state): bool => $state['deleted_at'] === null && (int) $state['active'] === 1);
            foreach ($ids as $id) {
                if (!$this->notifications->setActiveFromState($id, true, false)) {
                    throw new RuntimeException('notifications.validation.lifecycle_changed');
                }
                $events->record(new DomainEvent('system.notifications.deactivated', 'system.notifications', 'notification', (string) $id, ['active' => false]));
            }
        });
    }

    /**
     * Zpracovava hodnotu deletebatch pro oznameni
     * @param list<int> $ids
     */
    public function deleteBatch(array $ids): void
    {
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.delete', AuditActor::user($this->actor()->id())), function (TransactionalEventCollector $events) use ($ids): void {
            $states = $this->statesForBatch($ids, static fn(array $state): bool => $state['deleted_at'] === null);

            foreach ($ids as $id) {
                if (!$this->notifications->softDeleteNotification($id)) {
                    throw new RuntimeException('notifications.validation.lifecycle_changed');
                }

                $events->record(new DomainEvent('system.notifications.deleted', 'system.notifications', 'notification', (string) $id, ['active' => (int) $states[$id]['active'] === 1]));
            }
        });
    }

    /**
     * Zpracovava hodnotu activateandresetdisplaybatch pro oznameni
     * @param list<int> $ids
     */
    public function activateAndResetDisplayBatch(array $ids): void
    {
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.activate', AuditActor::user($this->actor()->id())), function (TransactionalEventCollector $events) use ($ids): void {
            $this->statesForBatch($ids, static fn(array $state): bool => $state['deleted_at'] === null && (int) $state['active'] === 0);
            foreach ($ids as $id) {
                if (!$this->notifications->setActiveFromState($id, false, true)) {
                    throw new RuntimeException('notifications.validation.lifecycle_changed');
                }
                $this->notifications->resetDisplayState($id);
                $events->record(new DomainEvent('system.notifications.activated', 'system.notifications', 'notification', (string) $id, ['active' => true]));
                $events->record(new DomainEvent('system.notifications.display_reset', 'system.notifications', 'notification', (string) $id));
            }
        });
    }

    /**
     * Zpracovava hodnotu resetdisplaybatch pro oznameni
     * @param list<int> $ids
     */
    public function resetDisplayBatch(array $ids): void
    {
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.reset_display', AuditActor::user($this->actor()->id())), function (TransactionalEventCollector $events) use ($ids): void {
            $this->statesForBatch($ids, static fn(array $state): bool => $state['deleted_at'] === null && (int) $state['active'] === 1);
            foreach ($ids as $id) {
                $this->notifications->resetDisplayState($id);
                $events->record(new DomainEvent('system.notifications.display_reset', 'system.notifications', 'notification', (string) $id));
            }
        });
    }

    /**
     * Zpracovava hodnotu restoreasinactivebatch pro oznameni
     * @param list<int> $ids
     */
    public function restoreAsInactiveBatch(array $ids): void
    {
        $this->events->execute(new AuditOperation('system.notifications', 'notifications.restore', AuditActor::user($this->actor()->id())), function (TransactionalEventCollector $events) use ($ids): void {
            $states = $this->statesForBatch($ids, static fn(array $state): bool => $state['deleted_at'] !== null);
            foreach ($ids as $id) {
                if (!$this->notifications->restoreNotification($id)) {
                    throw new RuntimeException('notifications.validation.lifecycle_changed');
                }
                if ((int) $states[$id]['active'] === 1 && !$this->notifications->setActiveFromState($id, true, false)) {
                    throw new RuntimeException('notifications.validation.lifecycle_changed');
                }
                $events->record(new DomainEvent('system.notifications.restored', 'system.notifications', 'notification', (string) $id, ['active' => false]));
            }
        });
    }

    /**
     * Zpracovava hodnotu validatebulkselection pro oznameni
     * @param list<int> $ids
     */
    public function validateBulkSelection(array $ids): void
    {
        $this->statesForBatch($ids, static fn(array $state): bool => true);
    }

    /**
     * Zpracovava hodnotu setactive pro oznameni
     */
    private function setActive(int $id, bool $active): void
    {
        $state = $this->state($id);
        if ($state['deleted_at'] !== null) {
            throw new RuntimeException('notifications.validation.deleted_cannot_change');
        }
        if ((int) $state['active'] === ($active ? 1 : 0)) {
            return;
        }
        $actor = $this->actor();
        $eventCode = $active ? 'system.notifications.activated' : 'system.notifications.deactivated';
        $operation = $active ? 'notifications.activate' : 'notifications.deactivate';
        $this->events->execute(new AuditOperation('system.notifications', $operation, AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $active, $eventCode): void {
            if (!$this->notifications->setActive($id, $active)) {
                throw new RuntimeException('notifications.validation.lifecycle_changed');
            }
            $events->record(new DomainEvent($eventCode, 'system.notifications', 'notification', (string) $id, ['active' => $active]));
        });
    }

    /**
     * Zpracovava hodnotu state pro oznameni
     * @return array{id:int,active:int,deleted_at:string|null}
     */
    private function state(int $id): array
    {
        $state = $this->notifications->lifecycleState($id);
        if ($state === null) {
            throw new RuntimeException('notifications.validation.not_found');
        }

        return $state;
    }

    /**
     * Zpracovava hodnotu statesforbatch pro oznameni
     * @param list<int> $ids
     * @param callable(array{id:int,active:int,deleted_at:string|null}):bool $valid
     * @return array<int, array{id:int,active:int,deleted_at:string|null}>
     */
    private function statesForBatch(array $ids, callable $valid): array
    {
        $states = $this->notifications->lifecycleStates($ids);
        if (count($states) !== count($ids)) {
            throw new RuntimeException('notifications.validation.not_found');
        }
        foreach ($ids as $id) {
            $state = $states[$id] ?? null;
            if ($state === null || !$valid($state)) {
                throw new RuntimeException('notifications.validation.lifecycle_changed');
            }
        }

        return $states;
    }

    /**
     * Zpracovava hodnotu actor pro oznameni
     */
    private function actor(): \Lemonade\Admin\Auth\AuthenticatedUser
    {
        return $this->actors->requireLocalUser();
    }
}
