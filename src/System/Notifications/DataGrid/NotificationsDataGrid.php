<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Notifications\DataGrid;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\Action\DataGridBulkActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionRisk;
use Lemonade\Admin\DataGrid\Cell\ScalarCell;
use Lemonade\Admin\DataGrid\Cell\StatusCell;
use Lemonade\Admin\DataGrid\Cell\StatusVariant;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridFilterDefinition;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Notification\Models\NotificationModel;
use Lemonade\Admin\Notification\NotificationPresentation;
use Lemonade\Admin\Notification\NotificationType;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Promita management oznameni do autorizovaneho DataGridu
 */
final class NotificationsDataGrid implements DataGridProviderInterface
{
    /**
     * Nastavuje management projekci, presentation a action zavislosti
     */
    public function __construct(
        private readonly NotificationModel $model,
        private readonly NotificationPresentation $presentation,
        private readonly TranslatorInterface $translator,
        private readonly ModuleActionPresentationFactory $actionPresentation,
        private readonly AuthorizationService $authorization,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Urci pravo potrebne pro nacteni management prehledu
     */
    public function permission(): string
    {
        return 'system.notifications.view';
    }

    /**
     * Deklaruje sloupce, lifecycle filtr a hromadne management akce
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        return new DataGridDefinition(
            id: 'notifications',
            columns: [
                new DataGridColumnDefinition(
                    key: 'type',
                    translationKey: 'notifications.fields.type',
                    sortKey: 'type',
                ),
                new DataGridColumnDefinition(
                    key: 'title',
                    translationKey: 'notifications.fields.title',
                    sortKey: 'title',
                ),
                new DataGridColumnDefinition(
                    key: 'audience',
                    translationKey: 'notifications.fields.audience',
                ),
                new DataGridColumnDefinition(
                    key: 'author',
                    translationKey: 'notifications.fields.author',
                    sortKey: 'author',
                ),
                new DataGridColumnDefinition(
                    key: 'status',
                    translationKey: 'notifications.fields.status',
                    sortKey: 'status',
                ),
                new DataGridColumnDefinition(
                    key: 'createdAt',
                    translationKey: 'notifications.fields.created_at',
                    sortKey: 'createdAt',
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
                                label: $this->translator->get('notifications.list.' . $status),
                            ),
                            ['active', 'inactive', 'deleted'],
                        ),
                    ),
                ),
            ],
            defaultSortKey: 'createdAt',
            defaultSortDirection: 'desc',
            defaultPageSize: 25,
            maximumPageSize: 100,
            defaultView: 'all',
            showAllView: true,
            bulkActions: $this->bulkActions(),
        );
    }

    /**
     * Promita filtrovane a serazene management zaznamy modelu na typed radky
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $list = $this->model->listForDataGrid($query);
        /**
         * @var list<DataGridRowDefinition> $rows
         */
        $rows = array_map(fn(array $notification): DataGridRowDefinition => $this->row($notification), $list->items());

        return new DataGridResult(
            items: $rows,
            page: $list->page(),
            perPage: $list->perPage(),
            total: $list->total(),
        );
    }

    /**
     * Sestavuje presentation radku bez nahrazeni serverove authorization a lifecycle validace
     *
     * @param array{id:int,type:string,title:string,active:int,deleted_at:string|null,created_at:string,author_first_name:string|null,author_last_name:string|null,author_email:string|null,audience:string} $notification
     */
    private function row(array $notification): DataGridRowDefinition
    {
        $type = NotificationType::tryFrom($notification['type']) ?? NotificationType::Info;

        return new DataGridRowDefinition(
            id: (int) $notification['id'],
            cells: [
                'type' => new ScalarCell(value: $this->translator->get('notifications.types.' . $type->value)),
                'title' => new ScalarCell(value: $notification['title']),
                'audience' => new ScalarCell(value: $this->audience($notification['audience'])),
                'author' => new ScalarCell(value: $this->presentation->author($notification, $this->translator)),
                'status' => $this->statusCell($notification),
                'createdAt' => new ScalarCell(value: $notification['created_at']),
            ],
            actions: $this->actions(
                id: (int) $notification['id'],
                active: (int) $notification['active'] === 1,
                deleted: $notification['deleted_at'] !== null,
            ),
        );
    }

    /**
     * Mapuje active a soft-delete stav na lifecycle bunku prehledu
     *
     * @param array{deleted_at:string|null,active:int,...} $notification
     */
    private function statusCell(array $notification): StatusCell
    {
        if ($notification['deleted_at'] !== null) {
            $translationKey = 'notifications.status.deleted';
        } elseif ((int) $notification['active'] === 1) {
            $translationKey = 'notifications.status.active';
        } else {
            $translationKey = 'notifications.status.inactive';
        }

        return new StatusCell(
            value: $this->translator->get($translationKey),
            variant: (int) $notification['active'] === 1 && $notification['deleted_at'] === null ? StatusVariant::Success : StatusVariant::Muted,
            translationKey: $translationKey,
        );
    }

    /**
     * Lokalizuje ulozene shrnuti publika pro management prehled
     */
    private function audience(string $audience): string
    {
        [$type, $summary] = explode(':', $audience, 2) + ['', ''];

        return $this->translator->get('notifications.audience_summary.' . ($type === 'roles' ? 'roles' : 'users'), ['audience' => $summary]);
    }

    /**
     * Sklada viditelne action podle lifecycle a aktualnich opravneni
     *
     * @return list<DataGridRowActionDefinition>
     */
    private function actions(int $id, bool $active, bool $deleted): array
    {
        if ($deleted) {
            $action = $this->actionPresentation->rowAction(
                moduleCode: 'system.notifications',
                action: 'restore',
                entityId: $id,
            );
            return $action === null ? [] : [$action];
        }
        $actions = [];
        if ($this->authorization->hasPermission('system.notifications.publish')) {
            $actions[] = (new DataGridRowActionDefinition(
                key: 'edit',
                label: $this->translator->get('admin.common.edit'),
                url: '#',
                method: 'GET',
                modalUrl: $this->urls->route(name: 'admin.api.modal.edit', params: ['module' => 'notifications', 'id' => $id]),
                modalSize: 'large',
                kind: DataGridRowActionKind::Modal,
                placement: DataGridRowActionPlacement::Secondary,
                refresh: true,
            ))->withIcon(AdminIcon::PencilSquare);
        }
        if ($active && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.notifications',
            action: 'deactivate',
            entityId: $id,
        )) !== null) {
            $actions[] = $action;
        }
        if (!$active && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.notifications',
            action: 'activate',
            entityId: $id,
        )) !== null) {
            $actions[] = $action;
        }
        if (($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.notifications',
            action: 'reset-display',
            entityId: $id,
        )) !== null) {
            $actions[] = $action;
        }
        if (($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.notifications',
            action: 'delete',
            entityId: $id,
        )) !== null) {
            $actions[] = $action;
        }
        return $actions;
    }

    /**
     * Sklada hromadne management akce pro podporovane status pohledy
     *
     * @return list<DataGridBulkActionDefinition>
     */
    private function bulkActions(): array
    {
        $endpoint = $this->urls->route(name: 'admin.system.module.ajax.create', params: ['module' => 'notifications']);
        $definitions = [
            ['bulk-deactivate', 'notifications.actions.deactivate', AdminIcon::PauseCircle, 'system.notifications.deactivate', ['active'], 'notifications.confirm.deactivate'],
            ['bulk-reset-display', 'notifications.actions.reset_display', AdminIcon::ArrowCounterclockwise, 'system.notifications.reset_display', ['active'], 'notifications.confirm.reset_display'],
            ['bulk-activate', 'notifications.actions.activate', AdminIcon::CheckCircle, 'system.notifications.activate', ['inactive'], 'notifications.confirm.activate'],
            ['bulk-restore', 'notifications.actions.restore_inactive', AdminIcon::ArrowCounterclockwise, 'system.notifications.restore', ['deleted'], 'notifications.confirm.restore_inactive'],
            ['bulk-delete', 'notifications.actions.delete', AdminIcon::Trash3, 'system.notifications.delete', ['active', 'inactive'], 'notifications.confirm.delete'],
        ];
        $actions = [];
        foreach ($definitions as [$code, $labelKey, $icon, $permission, $views, $confirmationKey]) {
            if (!$this->authorization->hasPermission($permission)) {
                continue;
            }
            $actions[] = new DataGridBulkActionDefinition(
                label: $this->translator->get($labelKey),
                icon: $icon,
                endpoint: $endpoint,
                action: $code,
                allowedViews: $views,
                confirmation: new \Lemonade\Admin\Action\Presentation\ConfirmationDefinition($confirmationKey),
                risk: $code === 'bulk-delete' ? DataGridRowActionRisk::Destructive : DataGridRowActionRisk::Normal,
            );
        }

        if ($this->authorization->hasPermission('system.notifications.view')) {
            $actions[] = new DataGridBulkActionDefinition(
                label: $this->translator->get('notifications.actions.export'),
                icon: AdminIcon::Export,
                endpoint: $this->urls->route(name: 'admin.notifications.export'),
                action: 'export',
                allowedViews: ['all', 'active', 'inactive', 'deleted'],
                download: true,
            );
        }

        return $actions;
    }
}
