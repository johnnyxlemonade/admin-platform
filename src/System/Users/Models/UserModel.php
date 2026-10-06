<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Models;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Admin\System\Users\Editor\UserEditorInput;
use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\QueryBuilder;
use RuntimeException;

/**
 * Zprostredkuje persistenci uzivatelu, jejich lifecycle a DataGrid projekci
 */
final class UserModel extends Model
{
    protected string $table = 'system_user';

    protected bool $useTimestamps = true;

    protected bool $useSoftDeletes = true;

    /**
     * @var list<string>
     */
    protected array $allowedFields = [
        'username',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'active',
        'version',
        'last_login_at',
    ];

    /**
     * Mapuje DataGrid filtr a razeni na uzivatele se stavem a aktivnimi rolemi
     *
     * @return QueryPage<array<string,mixed>>
     */
    public function listForDataGrid(DataGridQuery $query): QueryPage
    {
        $status = $query->filter('status');
        $total = $this->filteredUsers(
            $query->search(),
            $query->filter('role') ?? '',
            $status,
        )->countAllResults();

        $sorts = [
            'email' => 'u.email',
            'status' => 'u.active',
            'lastActivity' => 'u.last_login_at',
        ];
        $direction = $query->sortDirection() === 'desc' ? 'DESC' : 'ASC';
        $rows = $this->filteredUsers(
            $query->search(),
            $query->filter('role') ?? '',
            $status,
        )
            ->select(['u.id', 'u.email', 'u.active', 'u.deleted_at', 'u.last_login_at', 'u.updated_at'])
            ->selectRaw('GROUP_CONCAT(CASE WHEN r.deleted_at IS NULL THEN r.name END ORDER BY r.name SEPARATOR ", ") AS roles')
            ->selectRaw('MAX(CASE WHEN r.deleted_at IS NULL AND r.is_super_admin = 1 THEN 1 ELSE 0 END) AS is_super_admin')
            ->join('system_user_role ur', 'ur.user_id = u.id', 'LEFT')
            ->join('system_role r', 'r.id = ur.role_id', 'LEFT')
            ->groupBy('u.id')
            ->orderBy($sorts[$query->sortKey()], $direction)
            ->orderBy('u.id', 'ASC')
            ->limit($query->pageSize(), ($query->page() - 1) * $query->pageSize())
            ->getArray();

        return new QueryPage($rows, $query->page(), $query->pageSize(), $total);
    }

    /**
     * Nacita aktivniho uzivatele s hodnotami potrebnymi pro editor
     *
     * @return array<string,mixed>|null
     */
    public function findForEditor(int $id): ?array
    {
        return $this->query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'active',
                'version',
                'last_login_at',
                'updated_at',
                'created_at',
            ])
            ->where('id', $id)
            ->first();
    }

    /**
     * Nacita uzivatele vcetne soft-deleted stavu pro obnoveni
     *
     * @return array<string,mixed>|null
     */
    public function findForRestore(int $id): ?array
    {
        return $this->withDeleted()->query()
            ->select(['id', 'first_name', 'last_name', 'email', 'active', 'deleted_at'])
            ->where('id', $id)
            ->first();
    }

    /**
     * Overuje unikatnost e-mailu i mezi soft-deleted uzivateli
     */
    public function emailAvailable(string $email, ?int $exceptId = null): bool
    {
        $query = $this->withDeleted()->query()->where('email', $email);
        if ($exceptId !== null) {
            $query = $query->whereRaw('id != ?', [$exceptId]);
        }

        return !$query->exists();
    }

    /**
     * Vyhledava uzivatele podle e-mailu vcetne smazanych zaznamu pro installer
     *
     * @return array{id:int,deleted_at:string|null}|null
     */
    public function findByEmailIncludingDeleted(string $email): ?array
    {
        /**
         * @var array{id:int,deleted_at:string|null}|null $user
         */
        $user = $this->withDeleted()
            ->query()
            ->select(['id', 'deleted_at'])
            ->where('email', $email)
            ->first();

        return $user;
    }

    /**
     * Pocita aktivni uzivatele pro dashboard widget
     */
    public function activeDashboardCount(): int
    {
        return $this->query()
            ->where('active', 1)
            ->countAllResults();
    }

    /**
     * Vytvari lokalniho uzivatele s pocatecnim stavem a credentialem
     */
    public function createUser(
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone,
        string $passwordHash,
        bool $active,
    ): int {
        $id = $this->insert([
            'username' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password_hash' => $passwordHash,
            'active' => $active ? 1 : 0,
        ]);
        if (!is_int($id) && !is_string($id)) {
            throw new RuntimeException('User persistence did not return an identifier.');
        }

        return (int) $id;
    }

    /**
     * Uklada editorova data jen pri shode optimistic-lock verze zaznamu
     */
    public function updateEditorVersioned(int $id, int $expectedVersion, UserEditorInput $input): bool
    {
        $updated = $this->query()
            ->where([
                'id' => $id,
                'version' => $expectedVersion,
            ])
            ->set([
                'username' => $input->email(),
                'first_name' => $input->firstName(),
                'last_name' => $input->lastName(),
                'email' => $input->email(),
                'phone' => $input->phone(),
                'active' => $input->active() ? 1 : 0,
                'version' => $expectedVersion + 1,
                'updated_at' => $this->now(),
            ])
            ->update();

        return $updated && $this->db->affected_rows() === 1;
    }

    /**
     * Uklada hash lokalniho hesla uzivatele
     */
    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $this->query()
            ->where('id', $id)
            ->set([
                'password_hash' => $passwordHash,
                'updated_at' => $this->now(),
            ])
            ->update();
    }

    /**
     * Meni aktivitu uzivatele a posouva jeho optimistic-lock verzi
     */
    public function updateActive(int $id, bool $active): void
    {
        $this->db->query(
            'UPDATE system_user SET active = ?, version = version + 1, updated_at = ? WHERE id = ?',
            [$active ? 1 : 0, $this->now(), $id],
        );
    }

    /**
     * Provadi canonical soft delete uzivatele
     */
    public function softDelete(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Obnovuje uzivatele z canonical soft-deleted lifecycle stavu
     */
    public function restoreUser(int $id): bool
    {
        return $this->restore($id);
    }

    /**
     * Overuje existenci jineho aktivniho uzivatele se superadmin roli
     */
    public function anotherActiveSuperAdminExists(int $excludedUserId): bool
    {
        return $this->withDeleted()->query()
            ->from('system_user u')
            ->join('system_user_role ur', 'ur.user_id = u.id')
            ->join('system_role r', 'r.id = ur.role_id')
            ->where('u.deleted_at', null)
            ->where('u.active', 1)
            ->where('r.deleted_at', null)
            ->where('r.is_super_admin', 1)
            ->whereRaw('u.id != ?', [$excludedUserId])
            ->exists();
    }

    /**
     * Pocita aktivni uzivatele se superadmin roli pro grid presentation
     */
    public function activeSuperAdminCount(): int
    {
        return $this->withDeleted()->query()
            ->from('system_user u')
            ->join('system_user_role ur', 'ur.user_id = u.id')
            ->join('system_role r', 'r.id = ur.role_id')
            ->where('u.deleted_at', null)
            ->where('u.active', 1)
            ->where('r.deleted_at', null)
            ->where('r.is_super_admin', 1)
            ->countAllResults();
    }

    /**
     * Sestavuje query omezeny hledanim, roli a viditelnym lifecycle stavem
     */
    private function filteredUsers(string $search, string $role, ?string $status): QueryBuilder
    {
        $query = $this->withDeleted()->query()->from('system_user u');
        if ($status === 'deleted') {
            $query = $query->whereRaw('u.deleted_at IS NOT NULL');
        } else {
            $query = $query->where('u.deleted_at', null);
        }
        if ($search !== '') {
            $query = $query->whereLike('u.email', $search);
        }
        if ($status === 'active') {
            $query = $query->where('u.active', 1);
        }
        if ($status === 'inactive') {
            $query = $query->where('u.active', 0);
        }
        if ($role !== '') {
            $query = $query->whereRaw(
                'EXISTS (SELECT 1 FROM system_user_role ur2 JOIN system_role r2 ON r2.id = ur2.role_id WHERE ur2.user_id = u.id AND r2.deleted_at IS NULL AND r2.code = ?)',
                [$role],
            );
        }

        return $query;
    }
}
