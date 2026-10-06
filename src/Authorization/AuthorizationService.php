<?php

declare(strict_types=1);

namespace Lemonade\Admin\Authorization;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Framework\Database\Database;
use RuntimeException;

/**
 * Vyhodnocuje efektivni opravneni roli a uzivatelu vcetne jejich vyjimek
 */
final class AuthorizationService implements AuthorizationResolverInterface
{
    /** @var array<int, bool> */
    private array $superAdminUsers = [];

    /** @var array<int, bool> */
    private array $rootUsers = [];

    /** @var array<int, bool> */
    private array $superAdminRoles = [];

    /** @var array<int, list<array{code:string,name_key:string,module_code:string}>> */
    private array $effectiveUserPermissions = [];

    /** @var array<int, list<array{code:string,name_key:string,module_code:string}>> */
    private array $effectiveRolePermissions = [];

    /** @var array<int, array<int, list<array{code:string,name_key:string,module_code:string}>>> */
    private array $effectiveUserRolePermissions = [];

    /** @var array<int, array<string, string>> */
    private array $userOverrides = [];

    /** @var list<array{code:string,name_key:string,module_code:string}>|null */
    private ?array $allPermissions = null;

    /**
     * Nastavuje zdroj aktualni identity a autorizacni uloziste
     */
    public function __construct(
        private readonly CurrentPrincipalProviderInterface $currentUser,
        private readonly Database $database,
    ) {}

    /**
     * Overi opravneni aktualne prihlaseneho uzivatele
     */
    public function currentUserCan(string $permission): bool
    {
        $user = $this->currentUser->currentUser();

        return $user !== null && $this->can($user, $permission);
    }

    /**
     * Poskytuje zkracenou kontrolu opravneni aktualniho uzivatele
     */
    public function hasPermission(string $permission): bool
    {
        return $this->currentUserCan($permission);
    }

    /**
     * Odmitne pokracovani bez opravneni aktualniho uzivatele
     */
    public function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new RuntimeException('Permission denied.');
        }
    }

    /**
     * Overi opravneni konkretniho uzivatele vcetne superadmin bypassu
     */
    public function can(AuthenticatedUser $user, string $permission): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return in_array($permission, $this->effectivePermissionsForUser($user), true);
    }

    /**
     * Overi, zda ma uzivatel aktivni superadmin roli
     */
    public function isSuperAdmin(AuthenticatedUser $user): bool
    {
        return $this->superAdminUsers[$user->id()] ??= $this->database->select(
            'SELECT 1 FROM system_user_role ur JOIN system_role r ON r.id=ur.role_id WHERE ur.user_id=? AND r.deleted_at IS NULL AND r.is_super_admin=1 LIMIT 1',
            [$user->id()],
        ) !== [];
    }

    /**
     * Overi, zda ma uzivatel aktivni root roli
     */
    public function isRoot(AuthenticatedUser $user): bool
    {
        return $this->rootUsers[$user->id()] ??= $this->database->select(
            'SELECT 1 FROM system_user_role ur JOIN system_role r ON r.id=ur.role_id WHERE ur.user_id=? AND r.code=? AND r.deleted_at IS NULL AND r.is_super_admin=1 LIMIT 1',
            [$user->id(), 'root'],
        ) !== [];
    }

    /**
     * Overi, zda je aktivni role oznacena jako superadmin
     */
    public function isSuperAdminRole(int $roleId): bool
    {
        return $this->superAdminRoles[$roleId] ??= $this->database->select('SELECT 1 FROM system_role WHERE id = ? AND deleted_at IS NULL AND is_super_admin = 1 LIMIT 1', [$roleId]) !== [];
    }

    /**
     * Overi aktualne prirazeni aktivni role uzivateli
     */
    public function hasAssignedRole(AuthenticatedUser $user, int $roleId): bool
    {
        return $this->database->select(
            'SELECT 1 FROM system_user_role ur JOIN system_role r ON r.id = ur.role_id WHERE ur.user_id = ? AND ur.role_id = ? AND r.deleted_at IS NULL LIMIT 1',
            [$user->id(), $roleId],
        ) !== [];
    }

    /**
     * Overi existenci aktivni role
     */
    public function roleExists(int $roleId): bool
    {
        return $roleId > 0 && $this->database->select('SELECT 1 FROM system_role WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$roleId]) !== [];
    }

    /**
     * Overi, zda vsechny zadane kody existuji v canonical permission katalogu
     *
     * @param list<string> $permissionCodes
     */
    public function permissionCodesExist(array $permissionCodes): bool
    {
        $permissionCodes = array_values(array_unique($permissionCodes));
        if ($permissionCodes === []) {
            return true;
        }

        $placeholders = implode(', ', array_fill(0, count($permissionCodes), '?'));
        $rows = $this->database->select('SELECT COUNT(*) AS count FROM system_permission WHERE code IN (' . $placeholders . ')', $permissionCodes);

        return (int) ($rows[0]['count'] ?? 0) === count($permissionCodes);
    }

    /**
     * Vraci efektivni kody opravneni uzivatele
     *
     * @return list<string>
     */
    public function effectivePermissionsForUser(AuthenticatedUser $user): array
    {
        return array_column($this->effectivePermissionDetailsForUser($user), 'code');
    }

    /**
     * Vypocita zamcena efektivni opravneni pri hypoteticke zmene jedne prirazene role
     *
     * @param list<string> $rolePermissionCodes
     * @return list<string>
     */
    public function effectivePermissionsForUserWithRolePermissions(AuthenticatedUser $user, int $roleId, array $rolePermissionCodes): array
    {
        if ($this->isSuperAdmin($user)) {
            return array_column($this->allPermissionDetails(), 'code');
        }

        $assignedRoleIds = array_column($this->database->select(
            'SELECT role_id FROM system_user_role WHERE user_id = ? FOR UPDATE',
            [$user->id()],
        ), 'role_id');
        $assignedRoleIds = array_map(static fn(mixed $id): int => (int) $id, $assignedRoleIds);
        $permissions = $this->rolePermissionDetailsForUserExcludingRole($user->id(), $roleId);
        if (in_array($roleId, $assignedRoleIds, true)) {
            $catalog = [];
            foreach ($this->allPermissionDetails() as $permission) {
                $catalog[$permission['code']] = $permission;
            }
            foreach (array_unique($rolePermissionCodes) as $code) {
                if (isset($catalog[$code])) {
                    $permissions[] = $catalog[$code];
                }
            }
        }

        return array_column($this->applyUserOverrides($user->id(), $permissions, true), 'code');
    }

    /**
     * Vraci efektivni kody opravneni aktivni role
     *
     * @return list<string>
     */
    public function effectivePermissionsForRole(int $roleId): array
    {
        return array_column($this->effectivePermissionDetailsForRole($roleId), 'code');
    }

    /**
     * Vraci permission kody seskupene podle aktivnich roli
     *
     * @param list<int> $roleIds
     * @return array<int, list<string>>
     */
    public function permissionCodesForRoles(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_filter($roleIds, static fn(int $roleId): bool => $roleId > 0)));
        if ($roleIds === []) {
            return [];
        }

        $permissions = array_fill_keys($roleIds, []);
        $placeholders = implode(', ', array_fill(0, count($roleIds), '?'));
        foreach ($this->database->select(
            'SELECT rp.role_id, p.code FROM system_role_permission rp JOIN system_role r ON r.id = rp.role_id JOIN system_permission p ON p.id = rp.permission_id WHERE rp.role_id IN (' . $placeholders . ') AND r.deleted_at IS NULL ORDER BY rp.role_id, p.code',
            $roleIds,
        ) as $row) {
            $roleId = (int) $row['role_id'];
            if (isset($permissions[$roleId])) {
                $permissions[$roleId][] = (string) $row['code'];
            }
        }

        return $permissions;
    }

    /**
     * Vraci efektivni permission metadata uzivatele vcetne overrides
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    public function effectivePermissionDetailsForUser(AuthenticatedUser $user): array
    {
        return $this->effectiveUserPermissions[$user->id()] ??= $this->isSuperAdmin($user)
            ? $this->allPermissionDetails()
            : $this->applyUserOverrides($user->id(), $this->rolePermissionDetailsForUser($user->id()));
    }

    /**
     * Vraci efektivni permission metadata aktivni role
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    public function effectivePermissionDetailsForRole(int $roleId): array
    {
        if (isset($this->effectiveRolePermissions[$roleId])) {
            return $this->effectiveRolePermissions[$roleId];
        }
        if ($this->isSuperAdminRole($roleId)) {
            return $this->effectiveRolePermissions[$roleId] = $this->allPermissionDetails();
        }

        $rows = $this->database->select(
            'SELECT p.code, p.name_key, p.module_code FROM system_role_permission rp JOIN system_role r ON r.id = rp.role_id JOIN system_permission p ON p.id = rp.permission_id WHERE rp.role_id = ? AND r.deleted_at IS NULL ORDER BY p.code',
            [$roleId],
        );
        /** @var list<array{code:string,name_key:string,module_code:string}> $rows */

        return $this->effectiveRolePermissions[$roleId] = $rows;
    }

    /**
     * Vraci efektivni permission metadata konkretni role pro konkretniho uzivatele
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    public function effectivePermissionDetailsForUserRole(int $userId, int $roleId): array
    {
        if (isset($this->effectiveUserRolePermissions[$userId][$roleId])) {
            return $this->effectiveUserRolePermissions[$userId][$roleId];
        }
        if ($this->isSuperAdminRole($roleId)) {
            return $this->effectiveUserRolePermissions[$userId][$roleId] = $this->allPermissionDetails();
        }

        return $this->effectiveUserRolePermissions[$userId][$roleId] = $this->applyUserOverrides($userId, $this->effectivePermissionDetailsForRole($roleId));
    }

    /**
     * Vraci aktualni allow a deny overrides uzivatele
     *
     * @return array<string,string>
     */
    public function userPermissionOverrides(int $userId): array
    {
        if (isset($this->userOverrides[$userId])) {
            return $this->userOverrides[$userId];
        }
        $overrides = [];
        foreach ($this->database->select('SELECT p.code, up.effect FROM system_user_permission up JOIN system_permission p ON p.id = up.permission_id WHERE up.user_id = ? ORDER BY p.code', [$userId]) as $row) {
            $overrides[(string) $row['code']] = (string) $row['effect'];
        }

        return $this->userOverrides[$userId] = $overrides;
    }

    /**
     * Vraci complete permission katalog pro presentation vrstvy
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    public function permissionDetails(): array
    {
        return $this->allPermissionDetails();
    }

    /**
     * Overi own nebo any variantu akce podle vlastnictvi zaznamu
     */
    public function canOwnOrAny(string $action, int $ownerId): bool
    {
        $user = $this->currentUser->currentUser();
        if ($user === null) {
            return false;
        }

        return $this->can($user, $action . '_any')
            || ($user->id() === $ownerId && $this->can($user, $action . '_own'));
    }

    /**
     * Vraci identifikator aktualniho uzivatele, je-li prihlasen
     */
    public function currentUserId(): ?int
    {
        return $this->currentUser->currentUser()?->id();
    }

    /**
     * Nacte a uklada do request cache complete permission katalog
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    private function allPermissionDetails(): array
    {
        if ($this->allPermissions !== null) {
            return $this->allPermissions;
        }
        $rows = $this->database->select('SELECT code, name_key, module_code FROM system_permission ORDER BY code');
        /** @var list<array{code:string,name_key:string,module_code:string}> $rows */

        return $this->allPermissions = $rows;
    }

    /**
     * Nacte role grants vsech aktivnich roli uzivatele
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    private function rolePermissionDetailsForUser(int $userId): array
    {
        /** @var list<array{code:string,name_key:string,module_code:string}> $rows */
        $rows = $this->database->select(
            'SELECT DISTINCT p.code, p.name_key, p.module_code FROM system_user_role ur JOIN system_role r ON r.id = ur.role_id JOIN system_role_permission rp ON rp.role_id = ur.role_id JOIN system_permission p ON p.id = rp.permission_id WHERE ur.user_id = ? AND r.deleted_at IS NULL ORDER BY p.code',
            [$userId],
        );

        return $rows;
    }

    /**
     * Nacte role grants uzivatele bez jedne hypoteticky nahrazovane role
     *
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    private function rolePermissionDetailsForUserExcludingRole(int $userId, int $excludedRoleId): array
    {
        /** @var list<array{code:string,name_key:string,module_code:string}> $rows */
        $rows = $this->database->select(
            'SELECT DISTINCT p.code, p.name_key, p.module_code FROM system_user_role ur JOIN system_role r ON r.id = ur.role_id JOIN system_role_permission rp ON rp.role_id = ur.role_id JOIN system_permission p ON p.id = rp.permission_id WHERE ur.user_id = ? AND ur.role_id <> ? AND r.deleted_at IS NULL ORDER BY p.code',
            [$userId, $excludedRoleId],
        );

        return $rows;
    }

    /**
     * Aplikuje allow a deny overrides na role grants uzivatele
     *
     * @param list<array{code:string,name_key:string,module_code:string}> $permissions
     * @return list<array{code:string,name_key:string,module_code:string}>
     */
    private function applyUserOverrides(int $userId, array $permissions, bool $lock = false): array
    {
        $effective = [];
        foreach ($permissions as $permission) {
            $effective[$permission['code']] = $permission;
        }
        $query = 'SELECT p.code, p.name_key, p.module_code, up.effect FROM system_user_permission up JOIN system_permission p ON p.id = up.permission_id WHERE up.user_id = ?';
        if ($lock) {
            $query .= ' FOR UPDATE';
        }
        foreach ($this->database->select($query, [$userId]) as $override) {
            if ($override['effect'] === 'allow') {
                $effective[(string) $override['code']] = ['code' => (string) $override['code'], 'name_key' => (string) $override['name_key'], 'module_code' => (string) $override['module_code']];
                continue;
            }
            unset($effective[(string) $override['code']]);
        }
        ksort($effective);

        return array_values($effective);
    }

    /**
     * Vymaze request cache autorizacnich dat po uspesne mutaci
     */
    public function invalidate(): void
    {
        $this->superAdminUsers = [];
        $this->rootUsers = [];
        $this->superAdminRoles = [];
        $this->effectiveUserPermissions = [];
        $this->effectiveRolePermissions = [];
        $this->effectiveUserRolePermissions = [];
        $this->userOverrides = [];
        $this->allPermissions = null;
    }
}
