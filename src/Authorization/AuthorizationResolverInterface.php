<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Definuje rozhrani pro overeni opravneni
 */
interface AuthorizationResolverInterface
{
    /**
     * Overi, zda je uzivatel superadmin
     */
    public function isSuperAdmin(AuthenticatedUser $user): bool;

    /**
     * Overi, zda je role superadmin
     */
    public function isSuperAdminRole(int $roleId): bool;

    /**
     * Overi existenci role
     */
    public function roleExists(int $roleId): bool;

    /**
     * Overi existenci zadanych opravneni
     *
     * @param list<string> $permissionCodes
     */
    public function permissionCodesExist(array $permissionCodes): bool;

    /**
     * Vrati efektivni opravneni uzivatele
     *
     * @return list<string>
     */
    public function effectivePermissionsForUser(AuthenticatedUser $user): array;

    /**
     * Vrati efektivni opravneni role
     *
     * @return list<string>
     */
    public function effectivePermissionsForRole(int $roleId): array;

    /**
     * Vrati opravneni pro zadane role
     *
     * @param list<int> $roleIds
     * @return array<int, list<string>>
     */
    public function permissionCodesForRoles(array $roleIds): array;

    /**
     * Vrati podrobnosti efektivnich opravneni uzivatele
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    public function effectivePermissionDetailsForUser(AuthenticatedUser $user): array;

    /**
     * Vrati podrobnosti efektivnich opravneni role
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    public function effectivePermissionDetailsForRole(int $roleId): array;
}
