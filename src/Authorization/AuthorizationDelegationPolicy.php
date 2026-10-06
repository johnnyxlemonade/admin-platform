<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Overuje, ktera opravneni lze predat dal
 */
final class AuthorizationDelegationPolicy
{
    public function __construct(
        private readonly AuthorizationResolverInterface $authorization,
        private readonly PermissionCatalogRegistry $catalog,
        private readonly PermissionDependencyResolver $dependencies,
    ) {}

    /** @param list<string> $permissionCodes */
    public function canDelegatePermissions(AuthenticatedUser $actor, array $permissionCodes): bool
    {
        $permissionCodes = array_values(array_unique($permissionCodes));

        return count($this->delegablePermissionCodes($actor, $permissionCodes)) === count($permissionCodes);
    }

    /**
     * Returns the subset the actor may delegate. Existence is checked against the canonical
     * runtime catalog, so this path never performs a query per permission.
     *
     * @param list<string> $permissionCodes
     * @return list<string>
     */
    public function delegablePermissionCodes(AuthenticatedUser $actor, array $permissionCodes): array
    {
        $permissionCodes = array_values(array_unique($permissionCodes));
        $known = [];
        foreach ($permissionCodes as $permissionCode) {
            if ($this->catalog->definition($permissionCode) !== null) {
                $known[] = $permissionCode;
            }
        }
        if ($known === [] || $this->authorization->isSuperAdmin($actor)) {
            return $known;
        }

        $effective = array_fill_keys($this->authorization->effectivePermissionsForUser($actor), true);
        $delegable = [];
        foreach ($known as $permissionCode) {
            foreach ($this->dependencies->withPrerequisites([$permissionCode]) as $requiredCode) {
                $definition = $this->catalog->definition($requiredCode);
                if ($definition === null || $definition->delegation() === PermissionDelegation::SuperAdminOnly || !isset($effective[$requiredCode])) {
                    continue 2;
                }
            }
            $delegable[] = $permissionCode;
        }

        return $delegable;
    }

    /**
     * Resolves assignability for role rows just loaded from canonical persistence. Mutation paths
     * must keep using canAssignRole(), which independently validates an untrusted role ID.
     *
     * @param list<array{id:int,is_super_admin:int,...}> $roles
     * @return list<int>
     */
    public function assignableLoadedRoleIds(AuthenticatedUser $actor, array $roles): array
    {
        $roleIds = array_values(array_unique(array_map(static fn(array $role): int => (int) $role['id'], $roles)));
        if ($this->authorization->isSuperAdmin($actor)) {
            return $roleIds;
        }

        $permissionsByRole = $this->authorization->permissionCodesForRoles($roleIds);
        $assignable = [];
        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            if ((int) $role['is_super_admin'] === 1) {
                continue;
            }
            $permissions = $permissionsByRole[$roleId] ?? [];
            if ($this->canDelegatePermissions($actor, $permissions)) {
                $assignable[] = $roleId;
            }
        }

        return array_values(array_unique($assignable));
    }

    public function canAssignRole(AuthenticatedUser $actor, int $roleId): bool
    {
        if (!$this->authorization->roleExists($roleId)) {
            return false;
        }

        if ($this->authorization->isSuperAdmin($actor)) {
            return true;
        }

        if ($this->authorization->isSuperAdminRole($roleId)) {
            return false;
        }

        return $this->canDelegatePermissions($actor, $this->authorization->effectivePermissionsForRole($roleId));
    }
}
