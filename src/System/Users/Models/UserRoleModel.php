<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Models;

use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Zprostredkuje role assignmenty uzivatelu a canonical role data pro Users capability
 */
final class UserRoleModel extends Model
{
    protected string $table = 'system_role';

    /**
     * Nacita vsechny aktivni role pro filtr a vyber assignmentu uzivateli
     *
     * @return list<array{id:int,code:string,name:string,is_super_admin:int}>
     */
    public function allRoles(): array
    {
        /**
         * @var list<array{id:int,code:string,name:string,is_super_admin:int}> $roles
         */
        $roles = $this->query()
            ->select(['id', 'code', 'name', 'is_super_admin'])
            ->where('deleted_at', null)
            ->orderBy('name')
            ->orderBy('id')
            ->getArray();

        return $roles;
    }

    /**
     * Nacita aktivni role prirazene konkretnimu uzivateli
     *
     * @return list<array<string,mixed>>
     */
    public function rolesForUser(int $userId): array
    {
        return $this->query()
            ->from('system_role r')
            ->select(['r.id', 'r.code', 'r.name'])
            ->join('system_user_role ur', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->where('r.deleted_at', null)
            ->orderBy('r.name')
            ->getArray();
    }

    /**
     * Nacita identifikatory roli prirazene konkretnimu uzivateli
     *
     * @return list<int>
     */
    public function roleIdsForUser(int $userId): array
    {
        $rows = $this->userRoleQuery()
            ->select('role_id')
            ->where('user_id', $userId)
            ->orderBy('role_id')
            ->getArray();

        return array_map(static fn(array $row): int => (int) $row['role_id'], $rows);
    }

    /**
     * Overuje existenci aktivni role pouzitelne pro assignment
     */
    public function roleExists(int $roleId): bool
    {
        return $roleId > 0 && $this->query()->where('id', $roleId)->where('deleted_at', null)->exists();
    }

    /**
     * Nacita stabilni kod aktivni role pro auditni udalost
     */
    public function codeForRole(?int $roleId): ?string
    {
        if ($roleId === null) {
            return null;
        }

        $row = $this->query()
            ->select('code')
            ->where('id', $roleId)
            ->where('deleted_at', null)
            ->first();

        return $row === null ? null : (string) $row['code'];
    }

    /**
     * Overuje, zda nektera z danych roli nese superadmin status
     *
     * @param list<int> $roleIds
     */
    public function containsSuperAdminRole(array $roleIds): bool
    {
        return $roleIds !== []
            && $this->query()
                ->where('is_super_admin', 1)
                ->where('deleted_at', null)
                ->whereIn('id', $roleIds)
                ->exists();
    }

    /**
     * Nahrazuje assignment uzivatele jednou canonical roli
     */
    public function replaceForUser(int $userId, int $roleId): void
    {
        $this->userRoleQuery()
            ->where('user_id', $userId)
            ->delete();
        $this->userRoleQuery()
            ->set([
                'user_id' => $userId,
                'role_id' => $roleId,
            ])
        ->insert();
    }

    /**
     * Zajistuje existenci aktivni root role pro instalacni workflow
     */
    public function ensureRootRole(): int
    {
        $this->db->query(
            "INSERT INTO system_role (code, name, is_system, is_super_admin) VALUES ('root', 'Systémový správce', 1, 1) ON DUPLICATE KEY UPDATE name = VALUES(name), is_system = 1, is_super_admin = 1, deleted_at = NULL",
        );

        $role = $this->query()
            ->select(['id'])
            ->where('code', 'root')
            ->where('deleted_at', null)
            ->first();
        if ($role === null) {
            throw new \RuntimeException('Root role could not be created.');
        }

        return (int) $role['id'];
    }

    /**
     * Zajistuje canonical systemove role pro instalaci a development seed
     */
    public function ensureCanonicalSystemRoles(): void
    {
        foreach ([
            ['root', 'Systémový správce', 1],
            ['admin', 'Administrátor', 0],
            ['editor', 'Editor', 0],
        ] as [$code, $name, $superAdmin]) {
            $this->db->query(
                'INSERT INTO system_role (code, name, is_system, is_super_admin) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), is_system = 1, is_super_admin = VALUES(is_super_admin), deleted_at = NULL',
                [$code, $name, $superAdmin],
            );
        }
    }

    /**
     * Vrati query nad assignment tabulkou uzivatel-role
     */
    private function userRoleQuery(): QueryBuilder
    {
        return $this->query()->from('system_user_role');
    }
}
