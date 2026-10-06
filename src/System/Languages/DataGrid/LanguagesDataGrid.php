<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\DataGrid;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\Cell\FlagCell;
use Lemonade\Admin\DataGrid\Cell\FlagLinkCell;
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
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Admin\System\Languages\Models\LanguageModel;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Poskytuje DataGrid se stavem, vychozim jazykem a autorizovanymi akcemi
 */
final class LanguagesDataGrid implements DataGridProviderInterface
{
    /**
     * Nastavuje data a zavislosti pro lokalizovanou autorizovanou prezentaci
     */
    public function __construct(
        private readonly LanguageModel $languages,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationService $authorization,
        private readonly ModuleActionPresentationFactory $actionPresentation,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Deklaruje sloupce, filtrovani stavu a vychozi razeni prehledu
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        return new DataGridDefinition(
            id: 'languages',
            columns: [
                new DataGridColumnDefinition(
                    key: 'name',
                    translationKey: 'languages.fields.name',
                    sortKey: 'name',
                ),
                new DataGridColumnDefinition(
                    key: 'code',
                    translationKey: 'languages.fields.code',
                    sortKey: 'code',
                ),
                new DataGridColumnDefinition(
                    key: 'default',
                    translationKey: 'languages.fields.default',
                    sortKey: 'default',
                ),
                new DataGridColumnDefinition(
                    key: 'status',
                    translationKey: 'languages.fields.enabled',
                    sortKey: 'status',
                ),
                new DataGridColumnDefinition(
                    key: 'sortOrder',
                    translationKey: 'languages.fields.sort_order',
                    sortKey: 'sortOrder',
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
                        options: [
                            new SelectOptionDefinition(
                                value: 'enabled',
                                label: $this->translator->get('languages.list.enabled'),
                            ),
                            new SelectOptionDefinition(
                                value: 'disabled',
                                label: $this->translator->get('languages.list.disabled'),
                            ),
                        ],
                    ),
                ),
            ],
            defaultSortKey: 'sortOrder',
            defaultSortDirection: 'asc',
            defaultPageSize: 25,
            maximumPageSize: 100,
            defaultView: 'all',
            showAllView: true,
        );
    }

    /**
     * Urci pravo potrebne pro nacteni DataGridu
     */
    public function permission(): string
    {
        return 'system.languages.view';
    }

    /**
     * Prevadi vysledek modelu na typed radky DataGridu
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $list = $this->languages->listForDataGrid($query);

        return new DataGridResult(
            items: array_map(fn(array $language): DataGridRowDefinition => $this->row($language), $list->items()),
            page: $list->page(),
            perPage: $list->perPage(),
            total: $list->total(),
        );
    }

    /**
     * Sestavuje radek a neautoritativni viditelnost akci podle stavu a opravneni
     *
     * @param array{id:int,code:string,name:string,flag_code:string,enabled:int,is_default:int,sort_order:int} $language
     */
    private function row(array $language): DataGridRowDefinition
    {
        $enabled = (int) $language['enabled'] === 1;
        $isDefault = (int) $language['is_default'] === 1;
        $canEdit = $this->authorization->hasPermission('system.languages.edit');
        $actions = [];
        $modalUrl = $this->urls->route(
            name: 'admin.api.modal.edit',
            params: ['module' => 'languages', 'id' => $language['id']],
        );
        if ($canEdit) {
            $editAction = new DataGridRowActionDefinition(
                key: 'edit',
                label: $this->translator->get('admin.common.edit'),
                url: '#',
                method: 'GET',
                confirmation: null,
                modalUrl: $modalUrl,
                modalSize: 'medium',
                kind: DataGridRowActionKind::Modal,
                placement: DataGridRowActionPlacement::Secondary,
                refresh: true,
            );
            $actions[] = $editAction
                ->withAriaLabel($this->translator->get('languages.actions.edit_language', ['name' => $language['name']]))
                ->withIcon(AdminIcon::PencilSquare);
        }
        if ($enabled && !$isDefault && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.languages',
            action: 'disable',
            entityId: (int) $language['id'],
            placement: DataGridRowActionPlacement::Secondary,
        )) !== null) {
            $actions[] = $action->withAriaLabel(
                $this->translator->get('languages.actions.disable_language', ['name' => $language['name']]),
            );
        }
        if (!$enabled && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.languages',
            action: 'enable',
            entityId: (int) $language['id'],
            placement: DataGridRowActionPlacement::Secondary,
        )) !== null) {
            $actions[] = $action->withAriaLabel(
                $this->translator->get('languages.actions.enable_language', ['name' => $language['name']]),
            );
        }
        if ($enabled && !$isDefault && ($action = $this->actionPresentation->rowAction(
            moduleCode: 'system.languages',
            action: 'set-default',
            entityId: (int) $language['id'],
            placement: DataGridRowActionPlacement::Secondary,
        )) !== null) {
            $actions[] = $action->withAriaLabel(
                $this->translator->get('languages.actions.set_default_language', ['name' => $language['name']]),
            );
        }

        return new DataGridRowDefinition(
            id: $language['id'],
            cells: [
                'name' => $canEdit
                    ? new FlagLinkCell(
                        value: $language['name'],
                        url: '#',
                        flagCode: $language['flag_code'],
                        modalUrl: $modalUrl,
                        modalSize: 'medium',
                    )
                    : new FlagCell(
                        value: $language['name'],
                        flagCode: $language['flag_code'],
                    ),
                'code' => new ScalarCell(value: $language['code']),
                'default' => $isDefault
                        ? new StatusCell(
                            value: $this->translator->get('languages.list.default'),
                            variant: StatusVariant::Success,
                        )
                    : new ScalarCell(value: $this->translator->get('languages.list.not_default')),
                'status' => new StatusCell(
                    value: $this->translator->get($enabled ? 'languages.list.enabled' : 'languages.list.disabled'),
                    variant: $enabled ? StatusVariant::Success : StatusVariant::Muted,
                ),
                'sortOrder' => new ScalarCell(value: $language['sort_order']),
            ],
            actions: $actions,
        );
    }
}
