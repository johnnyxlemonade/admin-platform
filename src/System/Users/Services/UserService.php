<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Services;

use InvalidArgumentException;
use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
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
use RuntimeException;

/**
 * Ridi uzivatelske mutace, role assignmenty, permission overrides a bezpecnostni invarianty
 */
final class UserService
{
    /**
     * Nastavuje persistenci, delegaci, authorization a auditovanou transakci mutaci
     */
    public function __construct(
        private readonly UserModel $users,
        private readonly UserRoleModel $roles,
        private readonly UserPermissionOverrideModel $permissionOverrides,
        private readonly CurrentUserProvider $currentUser,
        private readonly AuthorizationDelegationPolicy $delegation,
        private readonly AuthorizationService $authorization,
        private readonly PermissionOverrideNormalizer $overrideNormalizer,
        private readonly PermissionDependencyResolver $dependencies,
        private readonly TransactionalEventProcessor $events,
        private readonly EditorLockManager $locks,
        private readonly LocalActorGuard $actors,
        private readonly ExternalIdentityRepository $identities,
    ) {}

    /**
     * Nacita uzivatele s jeho aktivnimi rolemi a informaci o externi identite
     *
     * @return array<string,mixed>
     */
    public function detail(int $id): array
    {
        $user = $this->user($id);
        $user['roles'] = $this->roles->rolesForUser($id);
        $user['has_external_identity'] = $this->identities->hasForUser($id) ? 1 : 0;

        return $user;
    }

    /**
     * Poskytuje aktivni role pro filtr a presentation assignmentu
     *
     * @return list<array{id:int,code:string,name:string,is_super_admin:int}>
     */
    public function roles(): array
    {
        return $this->roles->allRoles();
    }

    /**
     * Filtruje aktivni role na role delegovatelne aktualnim actorem
     *
     * @return list<array{id:int,code:string,name:string,is_super_admin:int}>
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
     * Overuje delegovatelnost role pro aktualniho actora
     */
    public function currentUserCanAssignRole(int $roleId): bool
    {
        $actor = $this->currentUser->currentUser();

        return $actor !== null && $this->delegation->canAssignRole($actor, $roleId);
    }

    /**
     * Overuje existenci aktivni role pouzitelne v editor inputu
     */
    public function roleExists(int $roleId): bool
    {
        return $this->roles->roleExists($roleId);
    }

    /**
     * Overuje unikatnost normalizovaneho e-mailu mimo editovany zaznam
     */
    public function emailAvailable(string $email, int $exceptId): bool
    {
        return $this->users->emailAvailable($email, $exceptId);
    }

    /**
     * Uklada editorovou zmenu s ochranou externi identity, self-service a delegace
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

        $this->events->execute(new AuditOperation('system.users', 'users.editor.save', AuditActor::user($actor->id())), function (TransactionalEventCollector $collector) use ($id, $input, $localPassword, $roleId, $roleChanged, $normalizedOverrides, $events): void {
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
     * Vytvari lokalniho uzivatele s delegovatelnou roli a lokalnim credentialem
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
        $this->events->execute(new AuditOperation('system.users', 'users.editor.create', AuditActor::user($actor->id())), function (TransactionalEventCollector $collector) use ($input, &$id, $localPassword, $roleId, $displayName): void {
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
     * Vytvari nebo overuje inicialni root ucet pro instalacni workflow
     */
    public function createInitialRoot(string $email, string $password): int
    {
        $email = LocalAuthenticationProvider::normalizeEmail($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email, 'UTF-8') > 254) {
            throw new InvalidArgumentException('users.validation.email_invalid');
        }
        if (mb_strlen($password, 'UTF-8') < 12) {
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
            new AuditOperation('system.users', 'users.installer.create', AuditActor::system('installer')),
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
     * Meni aktivitu uzivatele s ochranou vlastniho uctu a posledniho aktivniho superadmina
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
        $this->events->execute(new AuditOperation('system.users', $active ? 'users.activate' : 'users.deactivate', AuditActor::user($actorId)), function (TransactionalEventCollector $collector) use ($active, $id, $event): void {
            $this->users->updateActive($id, $active);
            $collector->record($event);
        });
        $this->invalidateAuthorizationState();
    }

    /**
     * Soft-deleteuje uzivatele s ochranou vlastniho uctu a superadmina
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
        $this->events->execute(new AuditOperation('system.users', 'users.delete', AuditActor::user($actorId)), function (TransactionalEventCollector $collector) use ($id, $event): void {
            if (!$this->users->softDelete($id)) {
                throw new RuntimeException('User soft delete failed.');
            }
            $this->locks->releaseOwnedByUser($id);
            $collector->record($event);
        });
        $this->invalidateAuthorizationState();
    }

    /**
     * Obnovuje pouze existujici soft-deleted uzivatelsky zaznam
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
        $this->events->execute(new AuditOperation('system.users', 'users.restore', AuditActor::user($actorId)), function (TransactionalEventCollector $collector) use ($id, $event): void {
            if (!$this->users->restoreUser($id)) {
                throw new RuntimeException('User restore failed.');
            }
            $collector->record($event);
        });
        $this->invalidateAuthorizationState();
    }

    /**
     * Vytvari delegovatelny nahled efektivnich prav zvolene role a aktualnich overrides
     *
     * @return array{roleId:int,permissionGroups:list<array{
     *     moduleCode:string,
     *     label:string,
     *     labelKey:string|null,
     *     icon:string|null,
     *     permissions:list<array{code:string,label:string,labelKey:string,state:string,requires:list<string>}>
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
     * Filtruje permission skupiny na rozsah delegovatelny aktualnim actorem
     *
     * @param list<array{
     *     moduleCode:string,
     *     label:string,
     *     labelKey:string|null,
     *     icon:string|null,
     *     permissions:list<array{code:string,label:string,labelKey:string,state:string,requires:list<string>}>
     * }> $permissionGroups
     * @return list<array{
     *     moduleCode:string,
     *     label:string,
     *     labelKey:string|null,
     *     icon:string|null,
     *     permissions:list<array{code:string,label:string,labelKey:string,state:string,requires:list<string>}>
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
     * Urci, zda cilovy uzivatel nese roli nebo efektivni prava mimo delegovatelny rozsah actora
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
     * Nacita aktivniho uzivatele nebo vyhodi typed not-found chybu
     *
     * @return array<string,mixed>
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
     * Odmita e-mail obsazeny aktivnim nebo smazanym uzivatelem
     */
    private function assertEmailAvailable(string $email, ?int $exceptId = null): void
    {
        if (!$this->users->emailAvailable($email, $exceptId)) {
            throw new InvalidArgumentException('This email address is already in use.');
        }
    }

    /**
     * Odmita roli, ktera neni aktivni nebo neexistuje
     */
    private function assertRoleId(int $roleId): void
    {
        if (!$this->roleExists($roleId)) {
            throw new InvalidArgumentException('The selected role does not exist.');
        }
    }

    /**
     * Odmita role assignment mimo delegovatelny rozsah aktualniho actora
     */
    private function assertRoleIsAssignable(int $roleId): void
    {
        if (!$this->currentUserCanAssignRole($roleId)) {
            throw new UserSafetyException('users.validation.role_not_assignable');
        }
    }

    /**
     * Chrani posledniho aktivniho superadmina pred ztratou role nebo aktivity
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
     * Overuje, zda sada roli obsahuje superadmin roli
     *
     * @param list<int> $roleIds
     */
    private function containsSuperAdminRole(array $roleIds): bool
    {
        return $this->roles->containsSuperAdminRole($roleIds);
    }

    /**
     * Nacita aktualni assignmenty roli uzivatele pro invarianty mutace
     *
     * @return list<int>
     */
    private function roleIds(int $id): array
    {
        return $this->roles->roleIdsForUser($id);
    }

    /**
     * Invaliduje request cache aktualniho uzivatele a authorization dat
     */
    private function invalidateAuthorizationState(): void
    {
        $this->currentUser->invalidate();
        $this->authorization->invalidate();
    }

    /**
     * Prevadi pozadovany efektivni stav na sparse override deltu vuci permission setu role
     *
     * @param array<string,bool> $requestedStates
     * @param list<string> $explicitlyDenied
     * @return array<string,string>
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
     * Zachovava nedelegovatelne overrides a overuje nove prime allow grants
     *
     * @param array<string,string> $oldOverrides
     * @param array<string,bool> $permissionStates
     * @return array<string,string>
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
     * Filtruje skupiny na permission kody delegovatelne aktualnim actorem
     *
     * @param list<array{
     *     moduleCode:string,
     *     label:string,
     *     labelKey:string|null,
     *     icon:string|null,
     *     permissions:list<array{code:string,label:string,labelKey:string,state:string,requires:list<string>}>
     * }> $permissionGroups
     * @return list<array{
     *     moduleCode:string,
     *     label:string,
     *     labelKey:string|null,
     *     icon:string|null,
     *     permissions:list<array{code:string,label:string,labelKey:string,state:string,requires:list<string>}>
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
     * Urci profilova pole skutecne zmenena editorovou mutaci
     *
     * @param array<string,mixed> $existing
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
     * Vrati kod role pro auditni payload nebo null bez prirazeni
     */
    private function roleCode(?int $roleId): ?string
    {
        return $this->roles->codeForRole($roleId);
    }

    /**
     * Sestavi zobrazovane jmeno uzivatele pro auditni udalosti
     *
     * @param array<string,mixed> $user
     */
    private function displayName(array $user): string
    {
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        return $name !== '' ? $name : (string) ($user['email'] ?? '');
    }

    /**
     * Sestavi auditni udalosti odpovidajici skutecne zmene uzivatele
     *
     * @param array<string,mixed> $existing
     * @param array<string,string> $oldOverrides
     * @param array<string,string>|null $normalizedOverrides
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
     * Vytvori domain udalost Users capability s omezenym auditnim payloadem
     *
     * @param array<string,mixed> $payload
     */
    private function event(string $code, int $id, array $payload): DomainEvent
    {
        return new DomainEvent($code, 'system.users', 'user', (string) $id, $payload);
    }
}
