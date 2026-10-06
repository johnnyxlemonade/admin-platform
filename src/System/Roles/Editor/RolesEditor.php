<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Editor;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Authorization\EffectivePermissionGroupViewModelFactory;
use Lemonade\Admin\Editor\Contract\EditorProviderInterface;
use Lemonade\Admin\Editor\EditorDefinition;
use Lemonade\Admin\Editor\EditorFieldDefinition;
use Lemonade\Admin\Editor\EditorSaveResult;
use Lemonade\Admin\Editor\Exception\EditorCapabilityException;
use Lemonade\Admin\Editor\Exception\EditorEntityNotFoundException;
use Lemonade\Admin\Editor\Exception\EditorValidationException;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\System\Roles\Exceptions\RoleAuthorizationException;
use Lemonade\Admin\System\Roles\Exceptions\RoleNotFoundException;
use Lemonade\Admin\System\Roles\Services\RoleService;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Validation\ValidationSchema;

/**
 * Propojuje editorovy transport s autorizovanymi mutacemi role a permission setu
 */
final class RolesEditor implements EditorProviderInterface
{
    /**
     * Nastavuje role service a validation schema editoru
     */
    public function __construct(
        private readonly RoleService $roles,
        private readonly EffectivePermissionGroupViewModelFactory $permissionGroups,
        private readonly RolesEditorValidationSchema $validation,
        private readonly AuthorizationService $authorization,
        private readonly CurrentPrincipalProviderInterface $principals,
    ) {}

    /**
     * Deklaruje editor prava a pole vcetne permission setu role
     */
    public function editorDefinition(): EditorDefinition
    {
        return new EditorDefinition(
            loadPermission: 'system.roles.edit',
            savePermission: 'system.roles.edit',
            createPermission: 'system.roles.create',
            fields: [
                new EditorFieldDefinition(name: 'code', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'name', type: 'text', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'description', type: 'textarea', readOnly: false, permission: null),
                new EditorFieldDefinition(name: 'permissions', type: 'permissions', readOnly: false, permission: null),
            ],
        );
    }

    /**
     * Nacita roli a delegovatelne permission groups pro editaci
     *
     * @return array{role:array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,permissions:list<string>},permissionGroups:list<array<string,mixed>>,rootProtected:bool}
     */
    public function updateData(int $id): array
    {
        try {
            $role = $this->roles->detail($id);
        } catch (RoleNotFoundException $exception) {
            throw new EditorEntityNotFoundException($exception->getMessage(), previous: $exception);
        }

        $actor = $this->principals->currentUser();
        $rootProtected = (int) $role['is_super_admin'] === 1 && ($actor === null || !$this->authorization->isSuperAdmin($actor));

        return ['role' => $role, 'permissionGroups' => $this->permissionGroups->create($this->roles->delegablePermissionDetails()), 'rootProtected' => $rootProtected];
    }

    /**
     * Poskytuje create data a delegovatelne permission groups
     *
     * @return array{role:array{id:null,code:string,name:string,description:null,permissions:list<never>},permissionGroups:list<array<string,mixed>>,rootProtected:false}
     */
    public function createData(): array
    {
        return ['role' => ['id' => null, 'code' => '', 'name' => '', 'description' => null, 'permissions' => []], 'permissionGroups' => $this->permissionGroups->create($this->roles->delegablePermissionDetails()), 'rootProtected' => false];
    }

    /**
     * Pouziva update schema bez nemenitelneho business kodu role
     */
    public function updateValidationSchema(int $id): ValidationSchema
    {
        return $this->validation->forUpdate();
    }

    /**
     * Pouziva create schema vcetne business kodu role
     */
    public function createValidationSchema(): ValidationSchema
    {
        return $this->validation->forCreate();
    }

    /**
     * Deleguje ulozeni role service a mapuje domainova odmitnuti na editor chyby
     */
    public function update(int $id, array $data): EditorSaveResult
    {
        try {
            $this->roles->update($id, trim((string) $data['name']), $this->description($data), $this->permissions($data));
        } catch (RoleNotFoundException $exception) {
            throw new EditorEntityNotFoundException($exception->getMessage(), previous: $exception);
        } catch (LocalActorRequiredException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (RoleAuthorizationException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::PERMISSION_DENIED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            throw new EditorValidationException($exception->getMessage(), previous: $exception);
        }

        return new EditorSaveResult([], 'roles.editor.saved');
    }

    /**
     * Deleguje vytvoreni role service a vraci ID pro navigaci editoru
     */
    public function create(array $data): EditorSaveResult
    {
        try {
            $id = $this->roles->create(trim((string) $data['code']), trim((string) $data['name']), $this->description($data), $this->permissions($data));
        } catch (LocalActorRequiredException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::LOCAL_ACTOR_REQUIRED,
                $exception->getMessage(),
            );
        } catch (RoleAuthorizationException $exception) {
            throw new EditorCapabilityException(
                HttpStatusCode::FORBIDDEN,
                AdminErrorCode::PERMISSION_DENIED,
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            throw new EditorValidationException($exception->getMessage(), previous: $exception);
        }

        return new EditorSaveResult(['id' => $id], 'roles.editor.created');
    }

    /**
     * Normalizuje prazdny popis na null
     *
     * @param array<string,mixed> $data
     */
    private function description(array $data): ?string
    {
        $description = trim((string) ($data['description'] ?? ''));

        return $description === '' ? null : $description;
    }

    /**
     * Normalizuje vybrane canonical kody opravneni z editor dat
     *
     * @param array<string,mixed> $data
     * @return list<string>
     */
    private function permissions(array $data): array
    {
        if (!isset($data['permissions']) || !is_array($data['permissions'])) {
            return [];
        }

        $permissions = [];
        foreach ($data['permissions'] as $code => $value) {
            if (is_string($code) && (string) $value === '1') {
                $permissions[] = $code;
            }
        }

        return $permissions;
    }
}
