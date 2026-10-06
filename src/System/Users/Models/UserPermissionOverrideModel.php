<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\Models;

use Lemonade\Framework\Database\Model;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Zprostredkuje persistenci explicitnich allow a deny overrides uzivatelskych opravneni
 */
final class UserPermissionOverrideModel extends Model
{
    protected string $table = 'system_user_permission';

    /**
     * Nacita ulozene explicitni override efekty podle permission kodu
     *
     * @return array<string,string>
     */
    public function forUser(int $userId): array
    {
        $rows = $this->query()
            ->from('system_user_permission up')
            ->select(['p.code', 'up.effect'])
            ->join('system_permission p', 'p.id = up.permission_id')
            ->where('up.user_id', $userId)
            ->orderBy('p.code')
            ->getArray();
        $overrides = [];
        foreach ($rows as $row) {
            $overrides[(string) $row['code']] = (string) $row['effect'];
        }

        return $overrides;
    }

    /**
     * Nahrazuje ulozene overrides novym sparse stavem normalizovanym service
     *
     * @param array<string,string> $overrides
     */
    public function replaceForUser(int $userId, array $overrides): void
    {
        $this->clearForUser($userId);
        if ($overrides === []) {
            return;
        }

        $permissionRows = $this->permissionQuery()
            ->select(['id', 'code'])
            ->whereIn('code', array_keys($overrides))
            ->getArray();
        $permissionIds = [];
        foreach ($permissionRows as $permission) {
            $permissionIds[(string) $permission['code']] = (int) $permission['id'];
        }
        foreach ($overrides as $code => $effect) {
            $permissionId = $permissionIds[$code] ?? null;
            if ($permissionId === null) {
                continue;
            }
            $this->query()
                ->set([
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'effect' => $effect,
                ])
                ->insert();
        }
    }

    /**
     * Odstranuje vsechny permission overrides uzivatele pri zmene role
     */
    public function clearForUser(int $userId): void
    {
        $this->query()
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Vrati query nad canonical katalogem opravneni pro ulozeni overrides
     */
    private function permissionQuery(): QueryBuilder
    {
        return $this->query()->from('system_permission');
    }
}
