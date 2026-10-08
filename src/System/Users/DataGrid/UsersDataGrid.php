<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Users\DataGrid;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Cell\DateTimeCell;
use Lemonade\Admin\DataGrid\Cell\ScalarCell;
use Lemonade\Admin\DataGrid\Cell\StatusCell;
use Lemonade\Admin\DataGrid\Cell\StatusVariant;
use Lemonade\Admin\DataGrid\Cell\ThumbnailCell;
use Lemonade\Admin\DataGrid\Cell\TranslationCell;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridFilterDefinition;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Presentation\AdminThumbnailComponent;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Admin\System\Users\Services\UserService;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Poskytuje DataGrid uzivatelu s neautoritativni presentation lifecycle akci
 */
final class UsersDataGrid implements DataGridProviderInterface
{
    /**
     * Nastavuje gridovy query source, role source a presentation zavislosti
     */
    public function __construct(
        private readonly UsersDataGridQuery $users,
        private readonly UserService $userService,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationService $authorization,
        private readonly ModuleActionPresentationFactory $actionPresentation,
        private readonly UrlGenerator $urls,
        private readonly AdminFileModel $files,
        private readonly AdminThumbnailComponent $thumbnails,
    ) {}

    /**
     * Definuje sloupce, filtry a strankovani Users DataGridu
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        return new DataGridDefinition(
            id: 'users',
            columns: [
                new DataGridColumnDefinition(
                    key: 'user',
                    translationKey: 'users.fields.user',
                ),
                new DataGridColumnDefinition(
                    key: 'email',
                    translationKey: 'users.fields.email',
                    sortKey: 'email',
                    class: 'd-none d-md-table-cell',
                ),
                new DataGridColumnDefinition(
                    key: 'status',
                    translationKey: 'users.fields.status',
                    sortKey: 'status',
                ),
                new DataGridColumnDefinition(
                    key: 'lastActivity',
                    translationKey: 'users.fields.last_login',
                    sortKey: 'lastActivity',
                    class: 'd-none d-lg-table-cell',
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
                        options: array_map(
                            fn(string $status): SelectOptionDefinition => new SelectOptionDefinition(
                                value: $status,
                                label: $this->translator->get('users.list.' . $status),
                            ),
                            ['active', 'inactive', 'deleted'],
                        ),
                    ),
                ),
                new DataGridFilterDefinition(
                    key: 'role',
                    optionSource: new StaticSelectOptionSource(
                        options: array_values(array_map(
                            static fn(array $role): SelectOptionDefinition => new SelectOptionDefinition(
                                value: $role['code'],
                                label: $role['name'],
                            ),
                            $this->userService->roles(),
                        )),
                    ),
                ),
            ],
            defaultSortKey: 'email',
            defaultSortDirection: 'asc',
            defaultPageSize: 20,
            maximumPageSize: 100,
        );
    }

    /**
     * Urci permission potrebne pro cteni Users DataGridu
     */
    public function permission(): string
    {
        return 'system.users.view';
    }

    /**
     * Promita query stranku do typed DataGrid response
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $list = $this->users->page($query);
        $avatars = $this->files->findForEntities('system.users', array_map(
            static fn(array $user): int => (int) $user['id'],
            $list->items(),
        ), 'thumbnail');

        return new DataGridResult(
            items: $this->rows($list->items(), $avatars),
            page: $list->page(),
            perPage: $list->perPage(),
            total: $list->total(),
        );
    }

    /**
     * Promita persistence radky do cells a neautoritativni action presentation
     *
     * @param list<array<string, mixed>> $users
     * @param array<int, array{id:int,module_code:string,entity_id:int,usage:string,kind:string,original_filename:string,display_name:string|null,caption:string|null,extension:string,mime_type:string,file_size:int,width:int|null,height:int|null}> $avatars
     * @return list<DataGridRowDefinition>
     */
    private function rows(array $users, array $avatars): array
    {
        $rows = [];
        foreach ($users as $user) {
            $email = (string) $user['email'];
            $active = (int) $user['active'] === 1;
            $deleted = $user['deleted_at'] !== null;
            $lastLogin = $user['last_login_at'];
            $roles = trim((string) ($user['roles'] ?? ''));
            $rows[] = new DataGridRowDefinition(
                id: (int) $user['id'],
                cells: [
                    'user' => new ThumbnailCell(
                        value: $email,
                        thumbnail: isset($avatars[(int) $user['id']])
                            ? $this->thumbnails->image(
                                module: 'system.users',
                                imageId: (string) $avatars[(int) $user['id']]['id'],
                                fallback: strtoupper(substr($email, 0, 1)),
                                size: 'compact',
                                shape: 'circle',
                                presentation: 'datagrid',
                            )
                            : $this->thumbnails->fallback(
                                fallback: strtoupper(substr($email, 0, 1)),
                                size: 'compact',
                                shape: 'circle',
                            ),
                        secondary: $roles !== '' ? $roles : $this->translator->get('users.list.no_role'),
                        url: $this->editUrl((int) $user['id'], $deleted),
                    ),
                    'email' => new ScalarCell(value: $email),
                    'status' => $this->statusCell(active: $active, deleted: $deleted),
                    'lastActivity' => $lastLogin === null
                        ? new TranslationCell(
                            translationKey: 'users.list.never_logged_in',
                            value: $this->translator->get('users.list.never_logged_in'),
                        )
                        : new DateTimeCell(value: (string) $lastLogin),
                ],
                actions: $this->rowActions(user: $user, active: $active, deleted: $deleted),
            );
        }

        return $rows;
    }

    /**
     * Sklada status cell odpovidajici aktivnimu nebo soft-deleted lifecycle stavu
     */
    private function statusCell(bool $active, bool $deleted): StatusCell
    {
        if ($deleted) {
            $translationKey = 'users.status.deleted';
        } elseif ($active) {
            $translationKey = 'users.status.active';
        } else {
            $translationKey = 'users.status.inactive';
        }

        return new StatusCell(
            value: $this->translator->get($translationKey),
            variant: $active && !$deleted ? StatusVariant::Success : StatusVariant::Muted,
            translationKey: $translationKey,
        );
    }

    /**
     * Sklada edit route pouze pro nesmazany zaznam a presentation autorizovaneho actora
     */
    private function editUrl(int $id, bool $deleted): ?string
    {
        return !$deleted && $this->authorization->hasPermission('system.users.edit')
            ? $this->urls->route(
                name: 'admin.system.module.edit',
                params: ['module' => 'users', 'id' => $id],
            )
            : null;
    }

    /**
     * Sklada pouze presentation dostupne row actions pro lifecycle stav uzivatele
     *
     * @param array<string, mixed> $user
     * @return list<DataGridRowActionDefinition>
     */
    private function rowActions(array $user, bool $active, bool $deleted): array
    {
        $id = (int) $user['id'];
        $items = [];
        if ($deleted) {
            if (($action = $this->actionPresentation->rowAction(
                moduleCode: 'system.users',
                action: 'restore',
                entityId: $id,
            )) !== null) {
                $items[] = $action;
            }

            return $items;
        }
        if ($this->authorization->hasPermission('system.users.edit')) {
            $items[] = (new DataGridRowActionDefinition(
                key: 'edit',
                label: $this->translator->get('admin.common.edit'),
                url: $this->urls->route(
                    name: 'admin.system.module.edit',
                    params: ['module' => 'users', 'id' => $id],
                ),
                method: 'GET',
            ))->withIcon(AdminIcon::PencilSquare);
        }
        if (!$active && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.users',
            action: 'activate',
            entityId: $id,
        )) !== null) {
            $items[] = $action;
        }
        /**
         * @var array{deactivate:bool,delete:bool} $eligibility
         */
        $eligibility = $user['action_eligibility'];
        if ($active && $eligibility['deactivate'] && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.users',
            action: 'deactivate',
            entityId: $id,
        )) !== null) {
            $items[] = $action;
        }
        if ($eligibility['delete'] && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.users',
            action: 'delete',
            entityId: $id,
        )) !== null) {
            $items[] = $action;
        }
        return $items;
    }
}
