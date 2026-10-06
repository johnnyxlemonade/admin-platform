<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Editor;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\EffectivePermissionGroupViewModelFactory;
use Lemonade\Admin\Editor\Contract\EditorAccessPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorLockBypassPolicyInterface;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorRecordConflictException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Admin\System\Users\Exceptions\UserNotFoundException;
use Lemonade\Admin\System\Users\Exceptions\UserRecordConflictException;
use Lemonade\Admin\System\Users\Exceptions\UserSafetyException;
use Lemonade\Admin\System\Users\Policies\UsersEditorAccessPolicy;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Propojuje Users editorovy transport s mutacemi a presentation projekcemi pristupu
 */
final class UsersEditor implements EditorProviderInterface, EditorAccessPolicyInterface, EditorLockBypassPolicyInterface
{
    /**
     * Nastavuje Users mutace, schema, authorization a self-service policy editoru
     */
    public function __construct(
        private readonly UserService $users,
        private readonly UsersEditorValidationSchema $validation,
        private readonly AuthorizationService $authorization,
        private readonly EffectivePermissionGroupViewModelFactory $permissionGroups,
        private readonly UsersEditorAccessPolicy $access,
    ) {}

    /**
     * Definuje field permissions, kde manage_roles ridi pouze role assignment field
     */
    public function editorDefinition(): EditorDefinition
    {
        return new EditorDefinition(
            loadPermission: 'system.users.edit',
            savePermission: 'system.users.edit',
            createPermission: 'system.users.create',
            fields: [
                new EditorFieldDefinition(name: 'first_name', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'last_name', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'email', type: 'email', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'phone', type: 'tel', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'active', type: 'boolean', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'version', type: 'hidden', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'role', type: 'select', readOnly: false, permission: 'system.users.manage_roles', optionSource: new StaticSelectOptionSource(array_map(static fn(array $role): SelectOptionDefinition => new SelectOptionDefinition((string) $role['id'], $role['name']), $this->users->assignableRoles()))),
                new EditorFieldDefinition(name: 'permissions', type: 'permission_states', readOnly: false, permission: 'system.users.manage_permissions'),
                new EditorFieldDefinition(name: 'local_password', type: 'password', readOnly: false, permission: null),
            ],
        );
    }

    /**
     * Deleguje self-service rozhodnuti pro nacteni editoru
     */
    public function loadAccessDecision(int $id): ?bool
    {
        return $this->access->loadAccessDecision($id);
    }

    /**
     * Deleguje self-service rozhodnuti pro ulozeni editoru
     */
    public function saveAccessDecision(int $id): ?bool
    {
        return $this->access->saveAccessDecision($id);
    }

    /**
     * Deleguje pravidlo bypassu ciziho editor locku
     */
    public function canBypassForeignLock(int $id): bool
    {
        return $this->access->canBypassForeignLock($id);
    }

    /**
     * Overuje, zda aktualni uzivatel muze ulozit vlastni profil bez vlastnictvi locku
     *
     * @param array<string, mixed> $payload
     */
    public function canSaveWithoutLock(int $id, array $payload): bool
    {
        return $this->access->canSaveWithoutLock($id, $payload);
    }

    /**
     * Nacita editor data, presentation hints a delegovatelne permission skupiny uzivatele
     *
     * @return array<string, mixed>
     */
    public function updateData(int $id): array
    {
        try {
            $user = $this->users->detail($id);
        } catch (UserNotFoundException $exception) {
            throw new EditorEntityNotFoundException($exception->getMessage(), previous: $exception);
        }
        $roleId = $user['roles'] === [] ? null : (int) $user['roles'][0]['id'];
        $isSelf = $this->authorization->currentUserId() === (int) $user['id'];
        $isSuperAdmin = $roleId !== null && $this->authorization->isSuperAdminRole($roleId);
        $isActive = (int) $user['active'] === 1;

        return [
            'user' => $user,
            'roleId' => $roleId,
            'roleAssignable' => !$isSelf && ($roleId === null || $this->users->currentUserCanAssignRole($roleId)),
            'roleEditable' => !$isSelf && $this->authorization->hasPermission('system.users.manage_roles') && ($roleId === null || $this->users->currentUserCanAssignRole($roleId)),
            'activeEditable' => !$isSelf && (!$isActive || $this->authorization->hasPermission('system.users.disable')),
            'permissionEditable' => !$isSelf && !$isSuperAdmin && $this->authorization->hasPermission('system.users.manage_permissions'),
            'hasProtectedAuthority' => !$isSelf && !$isSuperAdmin && $this->users->targetHasProtectedAuthority((int) $user['id']),
            'isSelf' => $isSelf,
            'isSuperAdmin' => $isSuperAdmin,
            'permissionGroups' => $this->users->delegablePermissionGroups($this->permissionGroups->createOverrideMatrix(
                $this->authorization->permissionDetails(),
                $roleId === null ? [] : $this->authorization->effectivePermissionDetailsForRole($roleId),
                $isSuperAdmin ? [] : $this->authorization->userPermissionOverrides((int) $user['id']),
            )),
        ];
    }

    /**
     * Poskytuje vychozi data pro zalozeni lokalniho uzivatele
     *
     * @return array<string, mixed>
     */
    public function createData(): array
    {
        return [
            'user' => ['id' => null, 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => null, 'active' => 1, 'version' => 1, 'identities' => []],
            'roleId' => null,
            'roleAssignable' => true,
            'roleEditable' => true,
            'activeEditable' => $this->authorization->hasPermission('system.users.disable'),
            'permissionEditable' => false,
            'hasProtectedAuthority' => false,
            'isSelf' => false,
            'isSuperAdmin' => false,
            'permissionGroups' => [],
        ];
    }

    /**
     * Pouziva schema pro update existujiciho uzivatele
     */
    public function updateValidationSchema(int $id): ValidationSchema
    {
        return $this->validation->forUpdate($id);
    }

    /**
     * Pouziva schema pro create lokalniho uzivatele
     */
    public function createValidationSchema(): ValidationSchema
    {
        return $this->validation->forCreate();
    }

    /**
     * Deleguje validated editor input do service a mapuje Users chyby na editor contract
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): EditorSaveResult
    {
        try {
            $this->users->updateEditor($id, UserEditorInput::fromValidated($data));
        } catch (LocalActorRequiredException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (UserNotFoundException $exception) {
            throw new EditorEntityNotFoundException($exception->getMessage(), previous: $exception);
        } catch (UserSafetyException $exception) {
            throw new EditorValidationException($exception->translationKey(), previous: $exception);
        } catch (UserRecordConflictException $exception) {
            throw new EditorRecordConflictException($exception->getMessage(), previous: $exception);
        }

        $editor = $this->updateData($id);

        return new EditorSaveResult(['version' => (int) $editor['user']['version']], 'users.editor.saved');
    }

    /**
     * Deleguje vytvoreni lokalniho uzivatele do service
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): EditorSaveResult
    {
        try {
            $id = $this->users->createEditor(UserEditorInput::fromValidated($data));
        } catch (LocalActorRequiredException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (UserSafetyException $exception) {
            throw new EditorValidationException($exception->translationKey(), previous: $exception);
        }

        return new EditorSaveResult(['id' => $id], 'users.editor.created');
    }
}
