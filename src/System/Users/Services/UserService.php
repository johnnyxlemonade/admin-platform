<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Services;

use InvalidArgumentException;
use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Auth\Mfa\MfaAuthenticationService;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\AuthorizationTargetPolicy;
use Lemonade\Admin\Authorization\EffectivePermissionGroupViewModelFactory;
use Lemonade\Admin\Authorization\PermissionDependencyResolver;
use Lemonade\Admin\Authorization\PermissionOverrideNormalizer;
use Lemonade\Admin\Editor\Lock\EditorLockManager;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\ExternalIdentityRepository;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Users\Editor\UserEditorInput;
use Lemonade\Admin\System\Users\Exceptions\UserNotFoundException;
use Lemonade\Admin\System\Users\Exceptions\UserRecordConflictException;
use Lemonade\Admin\System\Users\Exceptions\UserSafetyException;
use Lemonade\Admin\System\Users\Models\UserModel;
use Lemonade\Admin\System\Users\Models\UserPermissionOverrideModel;
use Lemonade\Admin\System\Users\Models\UserRoleModel;
use Lemonade\Admin\System\Users\Validation\UserPasswordPolicy;
use RuntimeException;

/**
 * Manages user mutations, role assignments, permission overrides, and safety invariants
 */
final readonly class UserService
{
    /**
     * Creates the audited user mutation service
     */
    public function __construct(
        private UserModel $users,
        private UserRoleModel $roles,
        private UserPermissionOverrideModel $permissionOverrides,
        private CurrentUserProvider $currentUser,
        private AuthorizationDelegationPolicy $delegation,
        private AuthorizationService $authorization,
        private PermissionOverrideNormalizer $overrideNormalizer,
        private PermissionDependencyResolver $dependencies,
        private TransactionalEventProcessor $events,
        private EditorLockManager $locks,
        private LocalActorGuard $actors,
        private ExternalIdentityRepository $identities,
        private ?AuthorizationTargetPolicy $targets = null,
        private ?TotpCredentialService $mfaCredentials = null,
        private ?MfaAuthenticationService $mfa = null,
    ) {}

    /**
     * Revokes a target user's TOTP credential after authorization
     */
    public function resetMfa(int $id, AuthenticatedUser $actor): bool
    {
        $target = $this->user($id);
        $targetUser = new AuthenticatedUser((int) $target['id'], (string) $target['email']);

        if ($this->targets === null || $this->mfaCredentials === null || $this->mfa === null || !$this->targets->canTargetUser($actor, $targetUser)) {
            throw new UserSafetyException('users.validation.mfa_target_not_allowed');
        }

        if (!$this->mfaCredentials->hasActiveCredential($id)) {
            return false;
        }

        $this->mfaCredentials->revoke($id);
        $this->mfa->reset($actor->id(), $id);

        return true;
    }

    /**
     * Returns a user with active roles and external identity information
     *
     * @return array<string, mixed>
     */
    public function detail(int $id): array
    {
        $user = $this->user($id);
        $user['roles'] = $this->roles->rolesForUser($id);
        $user['has_external_identity'] = $this->identities->hasForUser($id) ? 1 : 0;
        $user['mfa_active'] = $this->mfaCredentials?->hasActiveCredential($id) === true ? 1 : 0;

        return $user;
    }

    /**
     * Returns active roles for filtering and assignment presentation
     *
     * @return list<array{id: int, code: string, name: string, is_super_admin: int}>
     */
    public function roles(): array
    {
        return $this->roles->allRoles();
    }

    /**
     * Returns active roles delegable by the current actor
     *
     * @return list<array{id: int, code: string, name: string, is_super_admin: int}>
     */
    public function assignableRoles(): array
    {
        $actor = $this->currentUser->currentUser();

        if ($actor === null) {
            return [];
        }

        $roles = $this->roles();
        $assignable = array_fill_keys($this->delegation->assignableLoadedRoleIds($actor, $roles), true);

        return array_values(array_filter($roles, static fn(array $role): bool => isset($assignable[(int) $role['id']])));
    }

    /**
     * Reports whether the current actor can assign a role
     */
    public function currentUserCanAssignRole(int $roleId): bool
    {
        $actor = $this->currentUser->currentUser();

        return $actor !== null && $this->delegation->canAssignRole($actor, $roleId);
    }

    /**
     * Reports whether an active role is available to editor input
     */
    public function roleExists(int $roleId): bool
    {
        return $this->roles->roleExists($roleId);
    }

    /**
     * Reports whether a normalized email is available outside one record
     */
    public function emailAvailable(string $email, int $exceptId): bool
    {
        return $this->users->emailAvailable($email, $exceptId);
    }

    /**
     * Applies an editor update with identity, self-service, and delegation safeguards
     */
    public function updateEditor(int $id, UserEditorInput $input): void
    {
        $actor = $this->actors->requireLocalUser();

        $existing = $this->user($id);
        $hasExternalIdentity = $this->identities->hasForUser($id);

        if ($hasExternalIdentity && (
            $input->firstName() !== (string) $existing['first_name']
            || $input->lastName() !== (string) $existing['last_name']
            || $input->email() !== (string) $existing['email']
        )) {
            throw new UserSafetyException('users.validation.external_profile_readonly');
        }

        $currentRoleIds = $this->roleIds($id);
        $oldRoleId = $currentRoleIds[0] ?? null;
        $oldOverrides = $this->permissionOverrides->forUser($id);

        if ($id === $actor->id() && ($input->roleId() !== null || $input->permissionStates() !== null)) {
            throw new UserSafetyException('users.validation.cannot_change_own_authorization');
        }

        if ($id === $actor->id() && $input->active() !== ((int) $existing['active'] === 1)) {
            throw new UserSafetyException('users.validation.cannot_deactivate_self');
        }

        $currentActive = (int) $existing['active'] === 1;

        if ($currentActive && !$input->active() && !$this->authorization->hasPermission('system.users.disable')) {
            throw new UserSafetyException('users.validation.status_not_manageable');
        }

        $this->assertEmailAvailable($input->email(), $id);
        $roleId = $input->roleId();
        $roleChanged = $roleId !== null && $roleId !== $oldRoleId;

        if ($roleId !== null) {
            $this->assertRoleId($roleId);

            if ($roleChanged) {
                $this->assertRoleIsAssignable($roleId);
                $this->assertLastSuperAdminIsRetained($id, [$roleId], $input->active());
            }
        } elseif (!$input->active()) {
            $this->assertLastSuperAdminIsRetained($id, $currentRoleIds, false);
        }

        $permissionStates = $input->permissionStates();

        if ($permissionStates !== null && !$this->authorization->hasPermission('system.users.manage_permissions')) {
            throw new UserSafetyException('users.validation.permissions_not_manageable');
        }

        if ($permissionStates !== null && !$this->authorization->permissionCodesExist(array_keys($permissionStates))) {
            throw new UserSafetyException('users.validation.permission_invalid');
        }

        if ($permissionStates !== null && $this->containsSuperAdminRole($currentRoleIds)) {
            throw new UserSafetyException('users.validation.superadmin_overrides_forbidden');
        }

        $normalizedOverrides = $permissionStates === null ? null : $this->normalizedRequestedOverrides(
            $actor,
            $id,
            $roleId ?? ($currentRoleIds[0] ?? 0),
            $oldOverrides,
            $permissionStates,
            $roleChanged,
        );

        $localPassword = $input->localPassword();

        if ($localPassword !== null && $hasExternalIdentity) {
            throw new UserSafetyException('users.validation.external_password_forbidden');
        }

        $events = $this->updateEvents(
            $id,
            $existing,
            $input,
            $oldRoleId,
            $roleId,
            $roleChanged,
            $oldOverrides,
            $normalizedOverrides,
            $localPassword,
        );

        if ($events === []) {
            return;
        }

        $this->events->execute(AuditActor::user($actor->id()), function (TransactionalEventCollector $collector) use ($id, $input, $localPassword, $roleId, $roleChanged, $normalizedOverrides, $events): void {
            if (!$this->users->updateEditorVersioned($id, $input->version(), $input)) {
                throw new UserRecordConflictException('User record version no longer matches.');
            }

            if ($localPassword !== null) {
                $this->users->updatePasswordHash($id, LocalAuthenticationProvider::hashPassword($localPassword));
            }

            if ($roleId !== null) {
                $this->roles->replaceForUser($id, $roleId);
            }

            if ($roleChanged) {
                $this->permissionOverrides->clearForUser($id);
            } elseif ($normalizedOverrides !== null) {
                $this->permissionOverrides->replaceForUser($id, $normalizedOverrides);
            }

            foreach ($events as $event) {
                $collector->record($event);
            }
        });
        $this->invalidateAuthorizationState();
    }

    /**
     * Creates a local user with a delegable role and local credential
     */
    public function createEditor(UserEditorInput $input): int
    {
        $actor = $this->actors->requireLocalUser();
        $localPassword = $input->localPassword();
        $roleId = $input->roleId();

        if ($localPassword === null || $roleId === null) {
            throw new InvalidArgumentException('A local password and one role are required.');
        }

        if (!$input->active() && !$this->authorization->hasPermission('system.users.disable')) {
            throw new UserSafetyException('users.validation.status_not_manageable');
        }

        $this->assertEmailAvailable($input->email());
        $this->assertRoleId($roleId);
        $this->assertRoleIsAssignable($roleId);
        $id = 0;
        $displayName = $this->displayName([
            'first_name' => $input->firstName(),
            'last_name' => $input->lastName(),
            'email' => $input->email(),
        ]);
        $this->events->execute(AuditActor::user($actor->id()), function (TransactionalEventCollector $collector) use ($input, &$id, $localPassword, $roleId, $displayName): void {
            $id = $this->users->createUser(
                $input->firstName(),
                $input->lastName(),
                $input->email(),
                $input->phone(),
                LocalAuthenticationProvider::hashPassword($localPassword),
                $input->active(),
            );
            $this->roles->replaceForUser($id, $roleId);
            $collector->record($this->event('system.users.created', $id, ['user' => $displayName]));
        });
        $this->authorization->invalidate();

        return $id;
    }

    /**
     * Creates or verifies the initial root account for installation
     */
    public function createInitialRoot(string $email, string $password): int
    {
        $email = LocalAuthenticationProvider::normalizeEmail($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email, 'UTF-8') > 254) {
            throw new InvalidArgumentException('users.validation.email_invalid');
        }

        if (!UserPasswordPolicy::accepts($password)) {
            throw new InvalidArgumentException('users.validation.password_min_length');
        }

        $existing = $this->users->findByEmailIncludingDeleted($email);

        if ($existing !== null) {
            if ($existing['deleted_at'] !== null) {
                throw new InvalidArgumentException('users.validation.email_taken');
            }

            $rootRoleId = $this->roles->ensureRootRole();

            if (in_array($rootRoleId, $this->roles->roleIdsForUser((int) $existing['id']), true)) {
                return (int) $existing['id'];
            }

            throw new InvalidArgumentException('users.validation.email_taken');
        }

        $id = $this->events->execute(
            AuditActor::system('installer'),
            function (TransactionalEventCollector $events) use ($email, $password): int {
                $roleId = $this->roles->ensureRootRole();
                $id = $this->users->createUser(
                    'Root',
                    'Administrator',
                    $email,
                    null,
                    LocalAuthenticationProvider::hashPassword($password),
                    true,
                );
                $this->roles->replaceForUser($id, $roleId);
                $events->record(new DomainEvent('system.users.created', 'system.users', 'user', (string) $id, ['user' => $email]));

                return $id;
            },
        );
        $this->invalidateAuthorizationState();

        return $id;
    }

    /**
     * Changes user activity while protecting self and the last active super administrator
     */
    public function setActive(int $id, bool $active, int $actorId): void
    {
        $this->actors->requireLocalUser();

        $existing = $this->user($id);

        if (!$active && $id === $actorId) {
            throw new UserSafetyException('users.validation.cannot_deactivate_self');
        }

        if (!$active) {
            $this->assertLastSuperAdminIsRetained($id, $this->roleIds($id), false);
        }

        if ((int) $existing['active'] === ($active ? 1 : 0)) {
            return;
        }

        $event = $this->event($active ? 'system.users.activated' : 'system.users.deactivated', $id, ['user' => $this->displayName($existing)]);
        $this->events->execute(AuditActor::user($actorId), function (TransactionalEventCollector $collector) use ($active, $id, $event): void {
            $this->users->updateActive($id, $active);
            $collector->record($event);
        });

        $this->invalidateAuthorizationState();
    }

    /**
     * Soft deletes a user while protecting self and super administrator invariants
     */
    public function deleteUser(int $id, int $actorId): void
    {
        $actor = $this->actors->requireLocalUser();
        $existing = $this->user($id);

        if ($id === $actorId) {
            throw new UserSafetyException('users.validation.cannot_delete_self');
        }

        $roleIds = $this->roleIds($id);

        if ($this->containsSuperAdminRole($roleIds) && !$this->authorization->isSuperAdmin($actor)) {
            throw new UserSafetyException('users.validation.cannot_delete_superadmin');
        }

        if ((int) $existing['active'] === 1 && $this->containsSuperAdminRole($roleIds) && !$this->users->anotherActiveSuperAdminExists($id)) {
            throw new UserSafetyException('users.validation.last_administrator');
        }

        $event = $this->event('system.users.deleted', $id, ['user' => $this->displayName($existing)]);
        $this->events->execute(AuditActor::user($actorId), function (TransactionalEventCollector $collector) use ($id, $event): void {
            if (!$this->users->softDelete($id)) {
                throw new RuntimeException('User soft delete failed.');
            }
            $this->locks->releaseOwnedByUser($id);
            $collector->record($event);
        });

        $this->invalidateAuthorizationState();
    }

    /**
     * Restores an existing soft-deleted user record
     */
    public function restoreUser(int $id, int $actorId): void
    {
        $this->actors->requireLocalUser();

        $existing = $this->users->findForRestore($id);

        if ($existing === null) {
            throw new UserNotFoundException('User not found.');
        }

        if ($existing['deleted_at'] === null) {
            throw new UserSafetyException('users.validation.user_not_deleted');
        }

        $event = $this->event('system.users.restored', $id, ['user' => $this->displayName($existing)]);
        $this->events->execute(AuditActor::user($actorId), function (TransactionalEventCollector $collector) use ($id, $event): void {
            if (!$this->users->restoreUser($id)) {
                throw new RuntimeException('User restore failed.');
            }
            $collector->record($event);
        });

        $this->invalidateAuthorizationState();
    }

    /**
     * Creates a delegable preview of role permissions and current overrides
     *
     * @return array{roleId: int, permissionGroups: list<array{
     *     moduleCode: string,
     *     label: string,
     *     labelKey: string|null,
     *     icon: string|null,
     *     permissions: list<array{code: string, label: string, labelKey: string, state: string, requires: list<string>}>
     * }>}
     */
    public function permissionPreview(int $id, int $roleId, EffectivePermissionGroupViewModelFactory $groups): array
    {
        $actor = $this->currentUser->currentUser();

        if ($actor === null || !$this->currentUserCanAssignRole($roleId)) {
            throw new UserSafetyException('users.validation.role_not_assignable');
        }
        $this->user($id);
        $overrides = $this->authorization->isSuperAdminRole($roleId) ? [] : $this->permissionOverrides->forUser($id);

        return [
            'roleId' => $roleId,
            'permissionGroups' => $this->filterDelegablePermissionGroups($actor, $groups->createOverrideMatrix($this->authorization->permissionDetails(), $this->authorization->effectivePermissionDetailsForRole($roleId), $overrides)),
        ];
    }

    /**
     * Filters permission groups to the current actor's delegable scope
     *
     * @param list<array{
     *     moduleCode: string,
     *     label: string,
     *     labelKey: string|null,
     *     icon: string|null,
     *     permissions: list<array{code: string, label: string, labelKey: string, state: string, requires: list<string>}>
     * }> $permissionGroups
     * @return list<array{
     *     moduleCode: string,
     *     label: string,
     *     labelKey: string|null,
     *     icon: string|null,
     *     permissions: list<array{code: string, label: string, labelKey: string, state: string, requires: list<string>}>
     * }>
     */
    public function delegablePermissionGroups(array $permissionGroups): array
    {
        $actor = $this->currentUser->currentUser();

        if ($actor === null || !$this->authorization->hasPermission('system.users.manage_permissions')) {
            return [];
        }

        return $this->filterDelegablePermissionGroups($actor, $permissionGroups);
    }

    /**
     * Reports whether a target user has authority outside the actor's delegable scope
     */
    public function targetHasProtectedAuthority(int $id): bool
    {
        $actor = $this->currentUser->currentUser();

        if ($actor === null) {
            return false;
        }

        $roleIds = $this->roleIds($id);
        foreach ($roleIds as $roleId) {
            if (!$this->delegation->canAssignRole($actor, $roleId)) {
                return true;
            }
        }
        $targetPermissions = $this->authorization->effectivePermissionsForUser(new AuthenticatedUser($id, ''));

        if (count($this->delegation->delegablePermissionCodes($actor, $targetPermissions)) !== count(array_unique($targetPermissions))) {
            return true;
        }

        return false;
    }

    /**
     * Returns an active user or throws a typed not-found exception
     *
     * @return array<string, mixed>
     */
    private function user(int $id): array
    {
        $user = $this->users->findForEditor($id);

        if ($user === null) {
            throw new UserNotFoundException('User not found.');
        }

        return $user;
    }

    /**
     * Rejects an email used by an active or deleted user
     */
    private function assertEmailAvailable(string $email, ?int $exceptId = null): void
    {
        if (!$this->users->emailAvailable($email, $exceptId)) {
            throw new InvalidArgumentException('This email address is already in use.');
        }
    }

    /**
     * Rejects a missing or inactive role
     */
    private function assertRoleId(int $roleId): void
    {
        if (!$this->roleExists($roleId)) {
            throw new InvalidArgumentException('The selected role does not exist.');
        }
    }

    /**
     * Rejects role assignment outside the current actor's delegable scope
     */
    private function assertRoleIsAssignable(int $roleId): void
    {
        if (!$this->currentUserCanAssignRole($roleId)) {
            throw new UserSafetyException('users.validation.role_not_assignable');
        }
    }

    /**
     * Protects the last active super administrator from losing access
     *
     * @param list<int> $newRoleIds
     */
    private function assertLastSuperAdminIsRetained(int $id, array $newRoleIds, bool $active): void
    {
        $currentRoles = $this->roleIds($id);

        if (!$this->containsSuperAdminRole($currentRoles)) {
            return;
        }

        if ($active && $this->containsSuperAdminRole($newRoleIds)) {
            return;
        }

        if (!$this->users->anotherActiveSuperAdminExists($id)) {
            throw new UserSafetyException('users.validation.last_administrator');
        }
    }

    /**
     * Reports whether a role collection contains a super administrator role
     *
     * @param list<int> $roleIds
     */
    private function containsSuperAdminRole(array $roleIds): bool
    {
        return $this->roles->containsSuperAdminRole($roleIds);
    }

    /**
     * Returns current user role assignments for mutation invariants
     *
     * @return list<int>
     */
    private function roleIds(int $id): array
    {
        return $this->roles->roleIdsForUser($id);
    }

    /**
     * Invalidates current user and authorization request caches
     */
    private function invalidateAuthorizationState(): void
    {
        $this->currentUser->invalidate();
        $this->authorization->invalidate();
    }

    /**
     * Converts requested permission states to sparse role override deltas
     *
     * @param array<string, bool> $requestedStates
     * @param list<string> $explicitlyDenied
     * @return array<string, string>
     */
    private function normalizePermissionOverrides(int $roleId, array $requestedStates, array $explicitlyDenied = []): array
    {
        $rolePermissions = $this->dependencies->withPrerequisites($this->authorization->effectivePermissionsForRole($roleId));
        $requestedStates = $this->dependencies->withPrerequisitesInStates($requestedStates, $explicitlyDenied);

        return $this->overrideNormalizer->normalize(
            $rolePermissions,
            $requestedStates,
        );
    }

    /**
     * Preserves protected overrides and validates new direct allow grants
     *
     * @param array<string, string> $oldOverrides
     * @param array<string, bool> $permissionStates
     * @return array<string, string>
     */
    private function normalizedRequestedOverrides(AuthenticatedUser $actor, int $targetId, int $roleId, array $oldOverrides, array $permissionStates, bool $roleChanged): array
    {
        if ($roleChanged) {
            return [];
        }

        $currentEffective = array_fill_keys($this->authorization->effectivePermissionsForUser(new AuthenticatedUser($targetId, '')), true);
        $submittedDelegable = array_fill_keys($this->delegation->delegablePermissionCodes($actor, array_keys($permissionStates)), true);
        foreach ($permissionStates as $code => $allowed) {
            if (isset($submittedDelegable[$code])) {
                continue;
            }

            if ($allowed !== isset($currentEffective[$code])) {
                throw new UserSafetyException('users.validation.permission_not_delegable');
            }

            unset($permissionStates[$code]);
        }

        $requestedStates = [];
        foreach (array_keys($currentEffective) as $code) {
            $requestedStates[$code] = true;
        }

        foreach ($oldOverrides as $code => $effect) {
            if ($effect === 'deny') {
                $requestedStates[$code] = false;
            }
        }

        foreach ($permissionStates as $code => $allowed) {
            $requestedStates[$code] = $allowed;
        }

        $explicitlyDenied = array_keys(array_filter($permissionStates, static fn(bool $allowed): bool => !$allowed));
        $requestedStates = $this->dependencies->withPrerequisitesInStates($requestedStates, $explicitlyDenied);
        $normalizedOverrides = $this->normalizePermissionOverrides($roleId, $requestedStates, $explicitlyDenied);

        $addedAllows = [];
        foreach ($normalizedOverrides as $code => $effect) {
            if ($effect === 'allow' && ($oldOverrides[$code] ?? null) !== 'allow') {
                $addedAllows[] = $code;
            }
        }

        if (!$this->delegation->canDelegatePermissions($actor, $addedAllows)) {
            throw new UserSafetyException('users.validation.permission_not_delegable');
        }

        return $normalizedOverrides;
    }

    /**
     * Filters groups to permission codes delegable by the current actor
     *
     * @param list<array{
     *     moduleCode: string,
     *     label: string,
     *     labelKey: string|null,
     *     icon: string|null,
     *     permissions: list<array{code: string, label: string, labelKey: string, state: string, requires: list<string>}>
     * }> $permissionGroups
     * @return list<array{
     *     moduleCode: string,
     *     label: string,
     *     labelKey: string|null,
     *     icon: string|null,
     *     permissions: list<array{code: string, label: string, labelKey: string, state: string, requires: list<string>}>
     * }>
     */
    private function filterDelegablePermissionGroups(AuthenticatedUser $actor, array $permissionGroups): array
    {
        $permissionCodes = [];
        foreach ($permissionGroups as $group) {
            foreach ($group['permissions'] as $permission) {
                $permissionCodes[] = $permission['code'];
            }
        }

        $delegable = array_fill_keys($this->delegation->delegablePermissionCodes($actor, $permissionCodes), true);
        $delegableGroups = [];

        foreach ($permissionGroups as $group) {
            $permissions = array_values(array_filter(
                $group['permissions'],
                static fn(array $permission): bool => isset($delegable[$permission['code']]),
            ));

            if ($permissions === []) {
                continue;
            }

            $group['permissions'] = $permissions;
            $delegableGroups[] = $group;
        }

        return $delegableGroups;
    }

    /**
     * Returns profile fields changed by an editor mutation
     *
     * @param array<string, mixed> $existing
     * @return list<string>
     */
    private function changedProfileFields(array $existing, UserEditorInput $input): array
    {
        $changes = [];
        $fields = [
            'username' => $input->email(),
            'first_name' => $input->firstName(),
            'last_name' => $input->lastName(),
            'email' => $input->email(),
            'phone' => $input->phone(),
        ];
        foreach ($fields as $field => $value) {
            if (($existing[$field] ?? null) !== $value) {
                $changes[] = $field;
            }
        }

        return $changes;
    }

    /**
     * Returns a role code for audit payloads or null without an assignment
     */
    private function roleCode(?int $roleId): ?string
    {
        return $this->roles->codeForRole($roleId);
    }

    /**
     * Builds a user display name for audit events
     *
     * @param array<string, mixed> $user
     */
    private function displayName(array $user): string
    {
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        return $name !== '' ? $name : (string) ($user['email'] ?? '');
    }

    /**
     * Builds audit events for actual user changes
     *
     * @param array<string, mixed> $existing
     * @param array<string, string> $oldOverrides
     * @param array<string, string>|null $normalizedOverrides
     * @return list<DomainEvent>
     */
    private function updateEvents(int $id, array $existing, UserEditorInput $input, ?int $oldRoleId, ?int $roleId, bool $roleChanged, array $oldOverrides, ?array $normalizedOverrides, ?string $localPassword): array
    {
        $events = [];
        $displayName = $this->displayName($existing);
        $profileFields = $this->changedProfileFields($existing, $input);

        if ($profileFields !== []) {
            $events[] = $this->event('system.users.updated', $id, ['user' => $displayName, 'fields' => $profileFields]);
        }

        if ($localPassword !== null) {
            $events[] = $this->event('system.users.password_changed', $id, ['user' => $displayName]);
        }

        if ($roleChanged) {
            $events[] = $this->event('system.users.role_changed', $id, [
                'user' => $displayName,
                'oldRole' => $this->roleCode($oldRoleId),
                'newRole' => $this->roleCode($roleId),
            ]);
        }

        $currentOverrides = $roleChanged ? [] : $normalizedOverrides;
        ksort($oldOverrides);

        if ($currentOverrides !== null) {
            ksort($currentOverrides);
        }

        if ($currentOverrides !== null && $oldOverrides !== $currentOverrides) {
            $events[] = $this->event('system.users.permissions_changed', $id, ['user' => $displayName, 'permissions' => array_keys($currentOverrides)]);
        }

        if ((int) $existing['active'] !== ($input->active() ? 1 : 0)) {
            $events[] = $this->event($input->active() ? 'system.users.activated' : 'system.users.deactivated', $id, ['user' => $displayName]);
        }

        return $events;
    }

    /**
     * Creates a Users domain event with a restricted audit payload
     *
     * @param array<string, mixed> $payload
     */
    private function event(string $code, int $id, array $payload): DomainEvent
    {
        return new DomainEvent($code, 'system.users', 'user', (string) $id, $payload);
    }
}
