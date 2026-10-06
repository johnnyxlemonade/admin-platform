<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\AuthorizationTargetPolicy;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Notification\Models\NotificationModel;
use RuntimeException;

/**
 * Zpracovava zivotni cyklus nebo doruceni administracnich oznameni
 */
final class NotificationPublicationService
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani oznameni
     */
    public function __construct(private readonly NotificationModel $notifications, private readonly AuthorizationService $authorization, private readonly AuthorizationDelegationPolicy $delegation, private readonly AuthorizationTargetPolicy $targets, private readonly TransactionalEventProcessor $events) {}

    /**
     * Publikuje oznameni pro vybrane prijemce
     * @param array<int, int|string> $roleIds
     * @param array<int, int|string> $userIds
     */
    public function publish(AuthenticatedUser $actor, NotificationType $type, string $title, string $message, array $roleIds, array $userIds): int
    {
        [$roles, $users] = $this->audience($actor, $roleIds, $userIds);

        return $this->events->execute(new AuditOperation('system.notifications', 'notifications.publish', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($actor, $type, $title, $message, $roles, $users): int {
            $id = $this->notifications->createNotification($type, $title, $message, $actor->id());
            foreach ($roles as $role) {
                $this->notifications->addRoleAudience($id, $role);
            }
            foreach ($users as $user) {
                $this->notifications->addUserAudience($id, $user);
            }
            /** @var list<int> $recipients */
            $recipients = array_map(static fn(array $user): int => $user['id'], $users);
            $this->notifications->addRecipients($id, $recipients);
            $events->record(new DomainEvent('system.notifications.published', 'system.notifications', 'notification', (string) $id, ['type' => $type->value,'recipient_count' => count($recipients),'role_count' => count($roles),'user_count' => count($users)]));
            return $id;
        });
    }

    /**
     * Zpracovava hodnotu update pro oznameni
     * @param array<int, int|string> $roleIds
     * @param array<int, int|string> $userIds
     */
    public function update(AuthenticatedUser $actor, int $id, NotificationType $type, string $title, string $message, array $roleIds, array $userIds): void
    {
        [$roles, $users] = $this->audience($actor, $roleIds, $userIds);
        $notification = $this->notifications->notificationForEditor($id);
        if ($notification === null) {
            throw new RuntimeException('notifications.validation.not_found');
        }
        if ($notification['deleted_at'] !== null) {
            throw new RuntimeException('notifications.validation.deleted_cannot_edit');
        }
        if ($this->matchesEditorState($notification, $type, $title, $message, $roles, $users)) {
            return;
        }

        $this->events->execute(new AuditOperation('system.notifications', 'notifications.update', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $type, $title, $message, $roles, $users): void {
            if (!$this->notifications->updateNotification($id, $type, $title, $message)) {
                throw new RuntimeException('notifications.validation.lifecycle_changed');
            }
            $this->notifications->clearAudience($id);
            $this->notifications->resetDisplayState($id);
            foreach ($roles as $role) {
                $this->notifications->addRoleAudience($id, $role);
            }
            foreach ($users as $user) {
                $this->notifications->addUserAudience($id, $user);
            }
            $this->notifications->addRecipients($id, array_map(static fn(array $user): int => $user['id'], $users));
            $events->record(new DomainEvent('system.notifications.updated', 'system.notifications', 'notification', (string) $id, ['type' => $type->value]));
        });
    }

    /**
     * Porovnava editovatelne hodnoty a kanonicke publikum pred mutation
     *
     * @param array{id:int,type:string,title:string,message:string,active:int,deleted_at:string|null,role_ids:list<int>,user_ids:list<int>,audience_users:list<array{id:int,email:string}>} $notification
     * @param list<array{id:int,code:string,name:string}> $roles
     * @param list<array{id:int,email:string}> $users
     */
    private function matchesEditorState(array $notification, NotificationType $type, string $title, string $message, array $roles, array $users): bool
    {
        if ($notification['type'] !== $type->value || $notification['title'] !== $title || $notification['message'] !== $message) {
            return false;
        }

        $existingRoleIds = $notification['role_ids'];
        $requestedRoleIds = array_map(static fn(array $role): int => $role['id'], $roles);
        sort($existingRoleIds);
        sort($requestedRoleIds);
        if ($existingRoleIds !== $requestedRoleIds) {
            return false;
        }

        $existingUserIds = $notification['user_ids'];
        $requestedUserIds = array_map(static fn(array $user): int => $user['id'], $users);
        sort($existingUserIds);
        sort($requestedUserIds);

        return $existingUserIds === $requestedUserIds;
    }

    /**
     * Zpracovava hodnotu audience pro oznameni
     * @param array<int, int|string> $roleIds
     * @param array<int, int|string> $userIds
     * @return array{0:list<array{id:int,code:string,name:string}>,1:list<array{id:int,email:string}>}
     */
    private function audience(AuthenticatedUser $actor, array $roleIds, array $userIds): array
    {
        if (!$this->authorization->can($actor, 'system.notifications.publish')) {
            throw new RuntimeException('notifications.validation.audience_forbidden');
        }
        /** @var list<int> $roleIds */
        $roleIds = $this->ids($roleIds);
        /** @var list<int> $userIds */
        $userIds = $this->ids($userIds);
        if ($roleIds === [] && $userIds === []) {
            throw new RuntimeException('notifications.validation.audience_required');
        }
        if ($roleIds !== [] && $userIds !== []) {
            throw new RuntimeException('notifications.validation.audience_exclusive');
        }

        $roles = $this->notifications->activeRolesByIds($roleIds);
        $users = $this->notifications->activeUsersByIds($userIds);
        if (count($roles) !== count($roleIds)) {
            throw new RuntimeException('notifications.validation.role_invalid');
        }
        if (count($users) !== count($userIds)) {
            throw new RuntimeException('notifications.validation.user_invalid');
        }
        foreach ($roles as $role) {
            if (!$this->delegation->canAssignRole($actor, (int) $role['id'])) {
                throw new RuntimeException('notifications.validation.role_forbidden');
            }
        }
        foreach ($users as $user) {
            if (!$this->targets->canTargetUser($actor, new AuthenticatedUser((int) $user['id'], (string) $user['email']))) {
                throw new RuntimeException('notifications.validation.user_forbidden');
            }
        }

        return [$roles, $users];
    }

    /**
     * Zpracovava hodnotu ids pro oznameni
     * @param array<int, int|string> $ids
     * @return list<int>
     */
    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    }
}
