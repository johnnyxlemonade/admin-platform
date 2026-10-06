<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Services;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Authorization\AuthorizationDelegationPolicy;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Authorization\PermissionCatalogRegistry;
use Lemonade\Admin\Authorization\PermissionDependencyResolver;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Admin\Event\TransactionalEventCollector;
use Lemonade\Admin\Event\TransactionalEventProcessor;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\System\Roles\Exceptions\RoleAuthorizationException;
use Lemonade\Admin\System\Roles\Exceptions\RoleNotFoundException;
use Lemonade\Admin\System\Roles\Models\RoleModel;
use RuntimeException;

/**
 * Ridi autorizovane mutace roli, jejich permission set a ochranu proti eskalaci
 */
final class RoleService
{
    /**
     * Nastavuje persistenci, authorization, delegaci a auditovanou transakci mutaci
     */
    public function __construct(
        private readonly RoleModel $roles,
        private readonly AuthorizationService $authorization,
        private readonly AuthorizationDelegationPolicy $delegation,
        private readonly CurrentPrincipalProviderInterface $principals,
        private readonly PermissionCatalogRegistry $catalog,
        private readonly PermissionDependencyResolver $dependencies,
        private readonly TransactionalEventProcessor $events,
        private readonly LocalActorGuard $actors,
    ) {}

    /**
     * Nacita roli projection pro DataGrid
     *
     * @return QueryPage<array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,deleted_at:string|null,user_count:int}>
     */
    public function listForDataGrid(DataGridQuery $query): QueryPage
    {
        return $this->roles->listForDataGrid($query);
    }

    /**
     * Nacita roli s jeji efektivni permission mnozinou
     *
     * @return array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,permissions:list<string>}
     */
    public function detail(int $id): array
    {
        $role = $this->roles->findRole($id);
        if ($role === null) {
            throw new RoleNotFoundException('Role not found.');
        }

        return [...$role, 'permissions' => $this->authorization->effectivePermissionsForRole($id)];
    }

    /**
     * Vytvari vlastni roli s katalogove platnym delegovatelnym permission closure a auditem
     *
     * @param list<string> $permissions
     */
    public function create(string $code, string $name, ?string $description, array $permissions): int
    {
        $actor = $this->authorizedActor('system.roles.create');
        $code = trim($code);
        if (preg_match('/^[a-z][a-z0-9._-]{1,99}$/', $code) !== 1 || $this->roles->codeExists($code)) {
            throw new RuntimeException('roles.validation.code');
        }
        if ($this->roles->nameExists($name)) {
            throw new RuntimeException('roles.validation.name_taken');
        }
        $permissions = $this->validatedPermissions($permissions);
        $this->assertPermissionsDelegable($actor, $permissions);

        $id = $this->events->execute(new AuditOperation('system.roles', 'roles.editor.create', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($code, $name, $description, $permissions): int {
            $id = $this->roles->createRole($code, $name, $description);
            $this->roles->replacePermissions($id, $permissions);
            $events->record(new DomainEvent('system.roles.created', 'system.roles', 'role', (string) $id, ['code' => $code, 'name' => $name]));
            if ($permissions !== []) {
                $events->record(new DomainEvent('system.roles.permissions_changed', 'system.roles', 'role', (string) $id, ['added' => $permissions, 'removed' => []]));
            }

            return $id;
        });
        $this->authorization->invalidate();

        return $id;
    }

    /**
     * Uklada roli s ochranou root role, delegace a vlastniho efektivniho prava editace
     *
     * @param list<string> $permissions
     */
    public function update(int $id, string $name, ?string $description, array $permissions): void
    {
        $actor = $this->authorizedActor('system.roles.edit');
        $role = $this->roles->findRole($id);
        if ($role === null) {
            throw new RoleNotFoundException('Role not found.');
        }
        if ((int) $role['is_super_admin'] === 1 && !$this->authorization->isSuperAdmin($actor)) {
            throw new RuntimeException('roles.validation.root_protected');
        }
        if ($this->roles->nameExists($name, $id)) {
            throw new RuntimeException('roles.validation.name_taken');
        }
        $permissions = $this->validatedPermissions($permissions);
        $changed = [];
        if ((string) $role['name'] !== $name) {
            $changed['name'] = $name;
        }
        if ($role['description'] !== $description) {
            $changed['description'] = $description;
        }
        $previousPermissions = $this->authorization->effectivePermissionsForRole($id);
        $permissions = $this->preserveNonDelegablePermissions($actor, $previousPermissions, $permissions);
        $this->assertActorRetainsOwnRoleEditPermission($actor, $id, $permissions);
        sort($previousPermissions);
        $added = array_values(array_diff($permissions, $previousPermissions));
        $removed = array_values(array_diff($previousPermissions, $permissions));
        $events = [];
        if ($changed !== []) {
            $events[] = new DomainEvent('system.roles.updated', 'system.roles', 'role', (string) $id, ['changes' => $changed]);
        }
        if ($added !== [] || $removed !== []) {
            $events[] = new DomainEvent('system.roles.permissions_changed', 'system.roles', 'role', (string) $id, ['added' => $added, 'removed' => $removed]);
        }
        if ($events === []) {
            return;
        }

        $this->events->execute(new AuditOperation('system.roles', 'roles.editor.save', AuditActor::user($actor->id())), function (TransactionalEventCollector $collector) use ($id, $name, $description, $permissions, $events): void {
            if ($this->roles->findRoleForUpdate($id) === null) {
                throw new RoleNotFoundException('Role not found.');
            }

            $this->roles->updateRole($id, $name, $description);
            $this->roles->replacePermissions($id, $permissions);
            foreach ($events as $event) {
                $collector->record($event);
            }
        });
        $this->authorization->invalidate();
    }

    /**
     * Soft-deleteuje jen nesystemovou neroot roli v auditovane transakci
     */
    public function delete(int $id): void
    {
        $actor = $this->authorizedActor('system.roles.delete');
        $role = $this->roles->findRole($id);
        if ($role === null) {
            throw new RoleNotFoundException('Role not found.');
        }
        if ((int) $role['is_system'] === 1 || (int) $role['is_super_admin'] === 1) {
            throw new RuntimeException('roles.validation.system_protected');
        }
        $this->events->execute(new AuditOperation('system.roles', 'roles.delete', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $role): void {
            if (!$this->roles->softDelete($id)) {
                throw new RuntimeException('Role soft delete failed.');
            }
            $events->record(new DomainEvent('system.roles.deleted', 'system.roles', 'role', (string) $id, ['code' => (string) $role['code'], 'name' => (string) $role['name']]));
        });
        $this->authorization->invalidate();
    }

    /**
     * Obnovuje jen smazanou nesystemovou neroot roli v auditovane transakci
     */
    public function restore(int $id): void
    {
        $actor = $this->authorizedActor('system.roles.restore');
        $role = $this->roles->findRoleForRestore($id);
        if ($role === null) {
            throw new RoleNotFoundException('Role not found.');
        }
        if ($role['deleted_at'] === null) {
            throw new RuntimeException('roles.validation.role_not_deleted');
        }
        if ((int) $role['is_system'] === 1 || (int) $role['is_super_admin'] === 1) {
            throw new RuntimeException('roles.validation.system_protected');
        }
        $this->events->execute(new AuditOperation('system.roles', 'roles.restore', AuditActor::user($actor->id())), function (TransactionalEventCollector $events) use ($id, $role): void {
            if (!$this->roles->restoreRole($id)) {
                throw new RuntimeException('Role restore failed.');
            }
            $events->record(new DomainEvent('system.roles.restored', 'system.roles', 'role', (string) $id, ['code' => (string) $role['code'], 'name' => (string) $role['name']]));
        });
        $this->authorization->invalidate();
    }

    /**
     * Overuje katalog a rozsiruje vyber o permission dependency closure
     *
     * @param list<string> $permissions
     * @return list<string>
     */
    private function validatedPermissions(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        foreach ($permissions as $permission) {
            if ($this->catalog->definition($permission) === null) {
                throw new RuntimeException('roles.validation.permissions');
            }
        }

        return $this->dependencies->withPrerequisites($permissions);
    }

    /**
     * Poskytuje editoru jen permission, ktera aktualni aktor muze delegovat
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    public function delegablePermissionDetails(): array
    {
        $actor = $this->principals->currentUser();
        if ($actor === null || (!$this->authorization->can($actor, 'system.roles.create') && !$this->authorization->can($actor, 'system.roles.edit'))) {
            return [];
        }

        $permissions = $this->authorization->permissionDetails();
        $delegable = array_fill_keys($this->delegation->delegablePermissionCodes($actor, array_column($permissions, 'code')), true);

        return array_values(array_filter($permissions, static fn(array $permission): bool => isset($delegable[$permission['code']])));
    }

    /**
     * Vyzaduje lokalniho aktora s konkretnim management opravnenim pro mutaci role
     */
    private function authorizedActor(string $permission): AuthenticatedUser
    {
        $actor = $this->actors->requireLocalUser();
        if (!$this->authorization->can($actor, $permission)) {
            throw new RoleAuthorizationException('Permission denied.');
        }

        return $actor;
    }

    /**
     * Odmita create vyber mimo delegacni rozsah aktora
     *
     * @param list<string> $permissions
     */
    private function assertPermissionsDelegable(AuthenticatedUser $actor, array $permissions): void
    {
        if (!$this->delegation->canDelegatePermissions($actor, $permissions)) {
            throw new RuntimeException('roles.validation.permission_not_delegable');
        }
    }

    /**
     * Simuluje efektivni prava aktora po zmene vlastni role a chrani pravo editace
     *
     * @param list<string> $permissions
     */
    private function assertActorRetainsOwnRoleEditPermission(AuthenticatedUser $actor, int $roleId, array $permissions): void
    {
        if ($this->authorization->isSuperAdmin($actor)) {
            return;
        }

        $effectivePermissions = $this->authorization->effectivePermissionsForUserWithRolePermissions(
            user: $actor,
            roleId: $roleId,
            rolePermissionCodes: $permissions,
        );
        if (!in_array('system.roles.edit', $effectivePermissions, true)) {
            throw new RuntimeException('roles.validation.cannot_remove_own_edit_permission');
        }
    }

    /**
     * Zachovava nedelegovatelna existujici prava a odmita jejich nove pridani
     *
     * @param list<string> $previousPermissions
     * @param list<string> $requestedPermissions
     * @return list<string>
     */
    private function preserveNonDelegablePermissions(AuthenticatedUser $actor, array $previousPermissions, array $requestedPermissions): array
    {
        if ($this->authorization->isSuperAdmin($actor)) {
            return $requestedPermissions;
        }

        $previous = array_fill_keys($previousPermissions, true);
        $candidatePermissions = array_values(array_unique([...$previousPermissions, ...$requestedPermissions]));
        $delegable = array_fill_keys($this->delegation->delegablePermissionCodes($actor, $candidatePermissions), true);
        $preserved = [];
        foreach ($previousPermissions as $permission) {
            if (!isset($delegable[$permission])) {
                $preserved[] = $permission;
            }
        }
        $preserved = $this->dependencies->withPrerequisites($preserved);
        foreach ($requestedPermissions as $permission) {
            if (!isset($previous[$permission]) && !isset($delegable[$permission])) {
                throw new RuntimeException('roles.validation.permission_not_delegable');
            }
        }

        $permissions = array_values(array_unique([...$requestedPermissions, ...$preserved]));
        sort($permissions);

        return $permissions;
    }
}
