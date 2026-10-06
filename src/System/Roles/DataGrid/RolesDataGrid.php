<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Roles\DataGrid;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Cell\CodeCell;
use Lemonade\Admin\DataGrid\Cell\LinkCell;
use Lemonade\Admin\DataGrid\Cell\ScalarCell;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridFilterDefinition;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Admin\System\Roles\Services\RoleService;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Poskytuje DataGrid roli s neautoritativni presentation dostupnych akci
 */
final class RolesDataGrid implements DataGridProviderInterface
{
    /**
     * Nastavuje data, authorization a action presentation prehledu
     */
    public function __construct(
        private readonly RoleService $roles,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationService $authorization,
        private readonly CurrentPrincipalProviderInterface $principals,
        private readonly ModuleActionPresentationFactory $actionPresentation,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Deklaruje sloupce, status filtr a razeni prehledu
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        return new DataGridDefinition(
            id: 'roles',
            columns: [
                new DataGridColumnDefinition(
                    key: 'name',
                    translationKey: 'roles.fields.name',
                    sortKey: 'name',
                ),
                new DataGridColumnDefinition(
                    key: 'code',
                    translationKey: 'roles.fields.code',
                    sortKey: 'code',
                ),
                new DataGridColumnDefinition(
                    key: 'users',
                    translationKey: 'roles.fields.users',
                ),
                new DataGridColumnDefinition(
                    key: 'actions',
                    translationKey: 'admin.common.actions',
                ),
            ],
            searchEnabled: true,
            filters: [
                new DataGridFilterDefinition(
                    key: 'status',
                    optionSource: new StaticSelectOptionSource(
                        options: [
                            new SelectOptionDefinition(
                                value: 'active',
                                label: $this->translator->get('roles.list.active'),
                            ),
                            new SelectOptionDefinition(
                                value: 'deleted',
                                label: $this->translator->get('roles.list.deleted'),
                            ),
                        ],
                    ),
                ),
            ],
            defaultSortKey: 'name',
            defaultSortDirection: 'asc',
            defaultPageSize: 100,
            maximumPageSize: 100,
            defaultView: 'active',
            showAllView: false,
        );
    }

    /**
     * Urci pravo nutne pro cteni DataGridu
     */
    public function permission(): string
    {
        return 'system.roles.view';
    }

    /**
     * Promita filtrovany vysledek do typed radku DataGridu
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $list = $this->roles->listForDataGrid($query);

        return new DataGridResult(
            items: array_map(fn(array $role): DataGridRowDefinition => $this->row($role), $list->items()),
            page: $list->page(),
            perPage: $list->perPage(),
            total: $list->total(),
        );
    }

    /**
     * Sklada radek a neautoritativni viditelnost action podle stavu a opravneni
     *
     * @param array{id:int,code:string,name:string,description:string|null,is_system:int,is_super_admin:int,deleted_at:string|null,user_count:int} $role
     */
    private function row(array $role): DataGridRowDefinition
    {
        $deleted = $role['deleted_at'] !== null;
        $isCustom = (int) $role['is_system'] === 0 && (int) $role['is_super_admin'] === 0;
        $actor = $this->principals->currentUser();
        $editable = !$deleted && ((int) $role['is_super_admin'] === 0 || ($actor !== null && $this->authorization->isSuperAdmin($actor)));
        $actions = [];
        if ($editable && $this->authorization->hasPermission('system.roles.edit')) {
            $actions[] = (new DataGridRowActionDefinition(
                key: 'edit',
                label: $this->translator->get('admin.common.edit'),
                url: $this->editUrl((int) $role['id']),
                method: 'GET',
            ))->withIcon(AdminIcon::PencilSquare);
        }
        if (!$deleted && $isCustom && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.roles',
            action: 'delete',
            entityId: (int) $role['id'],
        )) !== null) {
            $actions[] = $action;
        }
        if ($deleted && $isCustom && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.roles',
            action: 'restore',
            entityId: (int) $role['id'],
        )) !== null) {
            $actions[] = $action;
        }

        return new DataGridRowDefinition(
            id: $role['id'],
            cells: [
                'name' => !$editable || !$this->authorization->hasPermission('system.roles.edit')
                    ? new ScalarCell(value: $role['name'])
                    : new LinkCell(
                        value: $role['name'],
                        url: $this->editUrl((int) $role['id']),
                    ),
                'code' => new CodeCell(value: $role['code']),
                'users' => new ScalarCell(value: $role['user_count']),
            ],
            actions: $actions,
        );
    }

    /**
     * Sklada canonical URL editoru role
     */
    private function editUrl(int $id): string
    {
        return $this->urls->route(
            name: 'admin.system.module.edit',
            params: ['module' => 'roles', 'id' => $id],
        );
    }
}
