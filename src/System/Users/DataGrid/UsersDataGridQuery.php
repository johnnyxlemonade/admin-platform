<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\DataGrid;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Admin\System\Users\Models\UserModel;

/**
 * Nacita radky prehledu uzivatelu a urcuje neautoritativni presentation dostupnost akci
 */
final readonly class UsersDataGridQuery
{
    /**
     * Nastavuje zdroje uzivatelskych radku a aktualni authorization kontext
     */
    public function __construct(
        private UserModel $users,
        private CurrentPrincipalProviderInterface $currentPrincipal,
        private AuthorizationService $authorization,
    ) {}

    /**
     * Doplni stranku uzivatelu o neautoritativni dostupnost akci radku
     *
     * @return QueryPage<array<string,mixed>>
     */
    public function page(DataGridQuery $query): QueryPage
    {
        $page = $this->users->listForDataGrid($query);
        $eligibility = $this->rowActionEligibility($page->items());
        /**
         * @var list<array<string,mixed>> $users
         */
        $users = array_map(static function (array $user) use ($eligibility): array {
            $user['action_eligibility'] = $eligibility[(int) $user['id']];
            return $user;
        }, $page->items());

        return new QueryPage($users, $page->page(), $page->perPage(), $page->total());
    }

    /**
     * Urci, zda presentation muze nabidnout deaktivaci nebo smazani kazdeho radku
     *
     * @param list<array<string,mixed>> $users
     * @return array<int, array{deactivate:bool,delete:bool}>
     */
    public function rowActionEligibility(array $users): array
    {
        $actor = $this->currentPrincipal->currentUser();
        $actorIsSuperAdmin = $actor !== null && $this->authorization->isSuperAdmin($actor);
        $needsActiveSuperAdminCount = false;
        foreach ($users as $user) {
            if ($user['deleted_at'] === null && (int) $user['active'] === 1 && (int) ($user['is_super_admin'] ?? 0) === 1) {
                $needsActiveSuperAdminCount = true;
                break;
            }
        }
        $activeSuperAdminCount = $needsActiveSuperAdminCount ? $this->users->activeSuperAdminCount() : 0;

        $eligibility = [];
        foreach ($users as $user) {
            $id = (int) $user['id'];
            $active = (int) $user['active'] === 1;
            $deleted = $user['deleted_at'] !== null;
            $isSuperAdmin = (int) ($user['is_super_admin'] ?? 0) === 1;
            $anotherActiveSuperAdminExists = !$active || !$isSuperAdmin || $activeSuperAdminCount > 1;
            $eligibility[$id] = [
                'deactivate' => $actor !== null
                    && $actor->id() !== $id
                    && (!$isSuperAdmin || $anotherActiveSuperAdminExists),
                'delete' => !$deleted
                    && $actor !== null
                    && $actor->id() !== $id
                    && (!$isSuperAdmin || $actorIsSuperAdmin)
                    && $anotherActiveSuperAdminExists,
            ];
        }

        return $eligibility;
    }
}
