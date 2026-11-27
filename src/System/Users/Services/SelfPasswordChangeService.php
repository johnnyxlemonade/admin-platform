<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Services;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\ExternalIdentityRepository;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Users\Exceptions\UserNotFoundException;
use Lemonade\Admin\System\Users\Exceptions\UserSafetyException;
use Lemonade\Admin\System\Users\Models\UserModel;
use Lemonade\Admin\System\Users\Validation\UserPasswordPolicy;

/**
 * Changes the current local user's password after verifying the existing credential
 */
final readonly class SelfPasswordChangeService
{
    /**
     * Creates the audited self-service password operation
     */
    public function __construct(
        private UserModel $users,
        private ExternalIdentityRepository $identities,
        private LocalAuthenticationProvider $authentication,
        private LocalActorGuard $actors,
        private TransactionalEventProcessor $events,
    ) {}

    /**
     * Verifies and replaces the current local user's password
     */
    public function change(int $userId, string $currentPassword, string $newPassword): void
    {
        $actor = $this->actors->requireLocalUser();

        if ($actor->id() !== $userId) {
            throw new UserSafetyException('users.validation.password_target_not_allowed');
        }

        $user = $this->users->findForEditor($userId);

        if ($user === null) {
            throw new UserNotFoundException('User not found.');
        }

        if ($this->identities->hasForUser($userId)) {
            throw new UserSafetyException('users.validation.external_password_forbidden');
        }

        $verified = $this->authentication->authenticate((string) $user['email'], $currentPassword)->user();

        if ($verified === null || $verified->id() !== $userId) {
            throw new UserSafetyException('users.validation.current_password_invalid');
        }

        if (!UserPasswordPolicy::accepts($newPassword)) {
            throw new UserSafetyException('users.validation.password_min_length');
        }

        $displayName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);

        if ($displayName === '') {
            $displayName = (string) $user['email'];
        }

        $this->events->execute(
            AuditActor::user($userId),
            function (TransactionalEventCollector $events) use ($userId, $newPassword, $displayName): void {
                $this->users->updatePasswordHash($userId, LocalAuthenticationProvider::hashPassword($newPassword));
                $events->record(new DomainEvent(
                    code: 'system.users.password_changed',
                    moduleCode: 'system.users',
                    entityType: 'user',
                    entityKey: (string) $userId,
                    payload: ['user' => $displayName],
                ));
            },
        );
    }
}
