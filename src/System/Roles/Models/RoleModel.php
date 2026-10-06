<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\Models;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Framework\Database\Model;

/**
 * Zprostredkuje persistenci roli, permission setu a soft-delete projekci
 */
final class RoleModel extends Model
{
    protected string $table = 'system_role';

    protected bool $useSoftDeletes = true;

    /**
     * Mapuje DataGrid filtr a razeni na role s poctem prirazeni uzivatelu
     *
     * @return QueryPage<array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,deleted_at:string|null,user_count:int}>
     */
    public function listForDataGrid(DataGridQuery $query): QueryPage
    {
        $search = $query->search();
        $sorts = ['name' => 'r.name', 'code' => 'r.code'];
        $direction = $query->sortDirection() === 'desc' ? 'DESC' : 'ASC';
        /**
         * @var list<array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,deleted_at:string|null,user_count:int}> $rows
         */
        $rows = $this->filteredRoles($search, $query->filter('status'))
            ->select(['r.id', 'r.code', 'r.name', 'r.description', 'r.is_system', 'r.is_super_admin', 'r.deleted_at'])
            ->selectRaw('COUNT(ur.user_id) AS user_count')
            ->join('system_user_role ur', 'ur.role_id = r.id', 'LEFT')
            ->groupBy('r.id')
            ->orderBy($sorts[$query->sortKey()], $direction)
            ->orderBy('r.id', 'ASC')
            ->limit($query->pageSize(), ($query->page() - 1) * $query->pageSize())
            ->getArray();

        return new QueryPage($rows, $query->page(), $query->pageSize(), $this->filteredRoles($search, $query->filter('status'))->countAllResults());
    }

    /**
     * Nacita aktivni roli pro detail nebo editaci
     *
     * @return array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int}|null
     */
    public function findRole(int $id): ?array
    {
        /**
         * @var array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int}|null $role
         */
        $role = $this->query()->select(['id', 'code', 'name', 'description', 'is_system', 'is_super_admin'])->where('id', $id)->first();

        return $role;
    }

    /**
     * Nacita aktivni roli pod zamkem pro soubezne autorizovane ulozeni
     *
     * @return array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int}|null
     */
    public function findRoleForUpdate(int $id): ?array
    {
        /**
         * @var array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int}|null $role
         */
        $role = $this->query()->select(['id', 'code', 'name', 'description', 'is_system', 'is_super_admin'])->where('id', $id)->lockForUpdate()->first();

        return $role;
    }

    /**
     * Nacita roli vcetne soft-deleted zaznamu pro obnoveni
     *
     * @return array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,deleted_at:string|null}|null
     */
    public function findRoleForRestore(int $id): ?array
    {
        /**
         * @var array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,deleted_at:string|null}|null $role
         */
        $role = $this->withDeleted()->query()->select(['id', 'code', 'name', 'description', 'is_system', 'is_super_admin', 'deleted_at'])->where('id', $id)->first();

        return $role;
    }

    /**
     * Overuje jedinecnost business kodu i mezi smazanymi rolemi
     */
    public function codeExists(string $code): bool
    {
        return $this->withDeleted()->query()->where('code', $code)->exists();
    }

    /**
     * Overuje jedinecnost nazvu mimo aktualne upravovanou roli
     */
    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $query = $this->withDeleted()->query()->where('name', $name);
        if ($exceptId !== null) {
            $query = $query->whereNotIn('id', [$exceptId]);
        }

        return $query->exists();
    }

    /**
     * Vytvari vlastni roli bez systemoveho nebo root priznaku
     */
    public function createRole(string $code, string $name, ?string $description): int
    {
        return (int) $this->insert(['code' => $code, 'name' => $name, 'description' => $description, 'is_system' => 0, 'is_super_admin' => 0]);
    }

    /**
     * Uklada pouze editovatelna metadata role
     */
    public function updateRole(int $id, string $name, ?string $description): void
    {
        $this->query()->where('id', $id)->set(['name' => $name, 'description' => $description])->update();
    }

    /**
     * Nahrazuje cely permission set canonicalnimi kody v ramci service transakce
     *
     * @param list<string> $permissionCodes
     */
    public function replacePermissions(int $id, array $permissionCodes): void
    {
        $this->rolePermissionQuery()->where('role_id', $id)->delete();
        if ($permissionCodes === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($permissionCodes), '?'));
        $this->db->query('INSERT INTO system_role_permission (role_id, permission_id) SELECT ?, id FROM system_permission WHERE code IN (' . $placeholders . ')', [$id, ...$permissionCodes]);
    }

    /**
     * Provadi soft delete role
     */
    public function softDelete(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Obnovuje drive soft-deleteovanou roli
     */
    public function restoreRole(int $id): bool
    {
        return $this->restore($id);
    }

    /**
     * Pripravi zaklad dotazu pro aktivni nebo smazane role
     */
    private function filteredRoles(string $search, ?string $status): \Lemonade\Framework\Database\QueryBuilder
    {
        $query = $this->withDeleted()->query()->from('system_role r');
        $query = $status === 'deleted'
            ? $query->whereRaw('r.deleted_at IS NOT NULL')
            : $query->where('r.deleted_at', null);
        if ($search !== '') {
            $query = $query->whereRaw('(r.name LIKE ? OR r.code LIKE ?)', ['%' . $search . '%', '%' . $search . '%']);
        }

        return $query;
    }

    /**
     * Pripravi dotaz nad prirazeni opravneni roli
     */
    private function rolePermissionQuery(): \Lemonade\Framework\Database\QueryBuilder
    {
        return $this->withDeleted()->query()->from('system_role_permission');
    }
}
