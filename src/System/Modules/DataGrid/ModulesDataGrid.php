<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\DataGrid;

use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
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
use Lemonade\Admin\Modules\Feature\ModuleFeatureManifestInterface;
use Lemonade\Admin\Modules\State\ModuleState;
use Lemonade\Admin\Modules\State\ModuleStateResolver;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Promita sdileny runtime stav modulu do management DataGridu
 */
final class ModulesDataGrid implements DataGridProviderInterface
{
    /**
     * Nastavuje runtime snapshot stavu a Admin presentation zavislosti
     */
    public function __construct(
        private readonly ModuleStateResolver $states,
        private readonly TranslatorInterface $translator,
        private readonly ModuleActionPresentationFactory $actionPresentation,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Urci pravo vyzadovane shared DataGrid transportem
     */
    public function permission(): string
    {
        return 'system.modules.view';
    }

    /**
     * Deklaruje sloupce, filtr druhu a razeni management prehledu
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        return new DataGridDefinition(
            id: 'modules',
            columns: [
                new DataGridColumnDefinition(
                    key: 'name',
                    translationKey: 'modules.fields.name',
                    sortKey: 'name',
                ),
                new DataGridColumnDefinition(
                    key: 'code',
                    translationKey: 'modules.fields.code',
                    sortKey: 'code',
                ),
                new DataGridColumnDefinition(
                    key: 'kind',
                    translationKey: 'modules.fields.kind',
                    sortKey: 'kind',
                ),
                new DataGridColumnDefinition(
                    key: 'state',
                    translationKey: 'modules.fields.state',
                    sortKey: 'state',
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
                                value: 'system',
                                label: $this->translator->get('modules.list.system'),
                            ),
                            new SelectOptionDefinition(
                                value: 'optional',
                                label: $this->translator->get('modules.list.optional'),
                            ),
                        ],
                    ),
                ),
            ],
            defaultSortKey: 'name',
            defaultSortDirection: 'asc',
            defaultPageSize: 25,
            maximumPageSize: 100,
            defaultView: 'all',
            showAllView: true,
        );
    }

    /**
     * Filtruje, radi a strankuje in-memory runtime stavy pro administracni prezentaci
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $moduleStates = $this->states->all();
        $moduleStates = array_values(array_filter($moduleStates, fn(ModuleState $state): bool => $this->matches($state, $query)));
        usort($moduleStates, fn(ModuleState $left, ModuleState $right): int => $this->compare($left, $right, $query));

        $total = count($moduleStates);
        $pageItems = array_slice($moduleStates, ($query->page() - 1) * $query->pageSize(), $query->pageSize());

        return new DataGridResult(
            items: array_map(fn(ModuleState $state): DataGridRowDefinition => $this->row($state), $pageItems),
            page: $query->page(),
            perPage: $query->pageSize(),
            total: $total,
        );
    }

    /**
     * Filtruje runtime stav podle kodu, lokalizovaneho nazvu a druhu modulu
     */
    private function matches(ModuleState $state, DataGridQuery $query): bool
    {
        $term = strtolower($query->search());
        $label = strtolower($this->label($state));
        if ($term !== '' && !str_contains(strtolower($state->code()), $term) && !str_contains($label, $term)) {
            return false;
        }
        $statusFilter = $query->filter('status');

        return $statusFilter === null || ($statusFilter === 'system' && $state->system()) || ($statusFilter === 'optional' && $state->available() && !$state->system());
    }

    /**
     * Radi podle zvolene presentation hodnoty a kodu jako stabilniho tie-breakeru
     */
    private function compare(ModuleState $leftState, ModuleState $rightState, DataGridQuery $query): int
    {
        $sortKey = $query->sortKey();
        $leftValue = $sortKey === 'name' ? $this->label($leftState) : ($sortKey === 'code' ? $leftState->code() : ($sortKey === 'kind' ? $this->kind($leftState) : $this->state($leftState)));
        $rightValue = $sortKey === 'name' ? $this->label($rightState) : ($sortKey === 'code' ? $rightState->code() : ($sortKey === 'kind' ? $this->kind($rightState) : $this->state($rightState)));

        $comparison = ($query->sortDirection() === 'desc' ? -1 : 1) * strcmp($leftValue, $rightValue);
        if ($comparison !== 0) {
            return $comparison;
        }

        return strcmp($leftState->code(), $rightState->code());
    }

    /**
     * Sklada radek s odvozenym lifecycle stavem a presentation akcemi
     */
    private function row(ModuleState $state): DataGridRowDefinition
    {
        return new DataGridRowDefinition(
            id: abs(crc32($state->code())),
            cells: [
                'name' => new ScalarCell(value: $this->label($state)),
                'code' => new ScalarCell(value: $state->code()),
                'kind' => new ScalarCell(value: $this->translator->get('modules.kind.' . $this->kind($state))),
                'state' => new StatusCell(
                    value: $this->translator->get('modules.state.' . $this->state($state)),
                    variant: StatusVariant::Muted,
                ),
            ],
            actions: $this->actions($state),
        );
    }

    /**
     * Lokalizuje nazev z manifestu nebo zachova kod osireleho runtime zaznamu
     */
    private function label(ModuleState $state): string
    {
        return $state->available() ? $this->translator->get($this->states->manifest($state->code())?->labelKey() ?? $state->code()) : $state->code();
    }

    /**
     * Urci presentation druh modulu z runtime stavu
     */
    private function kind(ModuleState $state): string
    {
        $kind = $state->kind();

        return $kind === null ? 'optional' : $kind->value;
    }

    /**
     * Mapuje runtime lifecycle stav na klic zobrazeny v DataGridu
     */
    private function state(ModuleState $state): string
    {
        return $state->missingCode() ? 'missing_code' : ($state->system() ? 'system' : (!$state->installed() ? 'available' : ($state->enabled() ? 'enabled' : 'disabled')));
    }

    /**
     * Pridava presentation akce bez nahrazeni serverove autorizace a lifecycle validace
     *
     * @return list<DataGridRowActionDefinition>
     */
    private function actions(ModuleState $state): array
    {
        $actions = [];
        $stateKey = $this->state($state);
        if ($stateKey !== 'system' && $stateKey !== 'missing_code') {
            $actionKey = $stateKey === 'available' ? 'install' : ($stateKey === 'disabled' ? 'enable' : 'disable');
            $action = $this->actionPresentation->rowAction(
                moduleCode: 'system.modules',
                action: $actionKey,
                entityId: abs(crc32($state->code())),
            );
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        $manifest = $state->available() ? $this->states->manifest($state->code()) : null;
        if ($state->available()
            && $manifest instanceof ModuleFeatureManifestInterface
            && $manifest->featureDefinitions() !== []) {
            $actions[] = (new DataGridRowActionDefinition(
                key: 'features',
                label: $this->translator->get('modules.actions.features'),
                url: $this->urls->route(
                    name: 'admin.modules.features',
                    params: ['module' => $state->code()],
                ),
                method: 'GET',
            ))->withIcon(AdminIcon::Sliders);
        }

        return $actions;
    }
}
