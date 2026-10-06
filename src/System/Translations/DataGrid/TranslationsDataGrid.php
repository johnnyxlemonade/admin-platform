<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Translations\DataGrid;

use Lemonade\Admin\Action\Presentation\ConfirmationDefinition;
use Lemonade\Admin\Action\Presentation\ModuleActionPresentationFactory;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\Action\DataGridBulkActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\Cell\LinkCell;
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
use Lemonade\Admin\System\Translations\TranslationsCatalog;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Poskytuje in-memory projekci read-only source katalogu a runtime override stavu
 */
final class TranslationsDataGrid implements DataGridProviderInterface
{
    /**
     * Nastavuje katalog, podporovane locale a presentation zavislosti gridu
     */
    public function __construct(
        private readonly TranslationsCatalog $catalog,
        private readonly LocalizationConfig $config,
        private readonly TranslatorInterface $translator,
        private readonly AuthorizationService $authorization,
        private readonly ModuleActionPresentationFactory $actionPresentation,
        private readonly UrlGenerator $urls,
    ) {}

    /**
     * Deklaruje sloupce a minimalni filtry projekce prekladu
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        $statusLabels = $this->statusLabels();

        return new DataGridDefinition(
            id: 'translations',
            columns: [
                new DataGridColumnDefinition(key: 'owner', translationKey: 'translations.fields.owner', sortKey: 'owner'),
                new DataGridColumnDefinition(key: 'group', translationKey: 'translations.fields.group', sortKey: 'group'),
                new DataGridColumnDefinition(key: 'key', translationKey: 'translations.fields.key', sortKey: 'key'),
                new DataGridColumnDefinition(key: 'source', translationKey: 'translations.fields.source', class: 'd-none d-xl-table-cell'),
                new DataGridColumnDefinition(key: 'effective', translationKey: 'translations.fields.effective', class: 'd-none d-xl-table-cell'),
                new DataGridColumnDefinition(key: 'status', translationKey: 'translations.fields.status', sortKey: 'status'),
                new DataGridColumnDefinition(key: 'actions', translationKey: 'admin.common.actions'),
            ],
            searchEnabled: false,
            filters: [
                new DataGridFilterDefinition('locale', new StaticSelectOptionSource($this->localeOptions())),
                new DataGridFilterDefinition('owner', new StaticSelectOptionSource($this->ownerOptions())),
                new DataGridFilterDefinition('status', new StaticSelectOptionSource([
                    new SelectOptionDefinition('default', $statusLabels['default']),
                    new SelectOptionDefinition('overridden', $statusLabels['overridden']),
                    new SelectOptionDefinition('missing', $statusLabels['missing']),
                ])),
            ],
            defaultSortKey: 'group',
            defaultSortDirection: 'asc',
            defaultPageSize: 25,
            maximumPageSize: 100,
            bulkActions: $this->bulkActions(),
        );
    }

    /**
     * Urci pravo potrebne pro cteni projekce prekladu
     */
    public function permission(): string
    {
        return 'system.translations.view';
    }

    /**
     * Filtruje, stabilne radi a strankuje zdrojovy katalog v pameti
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $locale = $query->filter('locale') ?? $this->config->defaultLocale;
        $entries = $this->catalog->entries($locale);
        $entries = array_values(array_filter($entries, function (array $entry) use ($query): bool {
            $owner = $query->filter('owner');
            if ($owner !== null && $entry['owner'] !== $owner) {
                return false;
            }
            $status = $query->filter('status');

            return $status === null || $status === $this->status($entry);
        }));
        usort($entries, function (array $left, array $right) use ($query): int {
            $sort = $query->sortKey();
            $leftValue = $sort === 'status' ? $this->status($left) : $left[$sort];
            $rightValue = $sort === 'status' ? $this->status($right) : $right[$sort];
            $comparison = strcmp((string) $leftValue, (string) $rightValue);
            if ($comparison === 0) {
                $comparison = [$left['group'], $left['key']] <=> [$right['group'], $right['key']];
            }

            return $query->sortDirection() === 'desc' ? -$comparison : $comparison;
        });
        $total = count($entries);
        $items = array_slice($entries, ($query->page() - 1) * $query->pageSize(), $query->pageSize());
        $statusLabels = $this->statusLabels();

        return new DataGridResult(
            items: array_map(fn(array $entry): DataGridRowDefinition => $this->row($entry, $statusLabels), $items),
            page: $query->page(),
            perPage: $query->pageSize(),
            total: $total,
        );
    }

    /**
     * @return list<SelectOptionDefinition>
     */
    private function localeOptions(): array
    {
        return array_map(fn(string $locale): SelectOptionDefinition => new SelectOptionDefinition($locale, $locale), $this->config->supportedLocales);
    }

    /**
     * @return list<SelectOptionDefinition>
     */
    private function ownerOptions(): array
    {
        return array_map(
            fn(string $owner): SelectOptionDefinition => new SelectOptionDefinition($owner, $owner),
            $this->catalog->owners(),
        );
    }

    /**
     * @param array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool} $entry
     * @param array{default:string,overridden:string,missing:string} $statusLabels
     */
    private function row(array $entry, array $statusLabels): DataGridRowDefinition
    {
        $canEdit = $this->authorization->hasPermission('system.translations.edit');
        $modalUrl = $this->urls->route(
            name: 'admin.api.modal.edit',
            params: ['module' => 'translations', 'id' => $entry['id']],
        );
        $actions = [];
        if ($canEdit) {
            $actions[] = (new DataGridRowActionDefinition(
                key: 'edit',
                label: $this->translator->get('admin.common.edit'),
                url: '#',
                method: 'GET',
                confirmation: null,
                modalUrl: $modalUrl,
                modalSize: 'large',
                kind: DataGridRowActionKind::Modal,
                placement: DataGridRowActionPlacement::Secondary,
                refresh: true,
            ))->withIcon(AdminIcon::PencilSquare);
        }
        if ($entry['overridden'] && ($reset = $this->actionPresentation->rowAction(
            moduleCode: 'system.translations',
            action: 'reset',
            entityId: $entry['id'],
            placement: DataGridRowActionPlacement::Secondary,
        )) !== null) {
            $actions[] = $reset;
        }

        return new DataGridRowDefinition(
            id: $entry['id'],
            cells: [
                'owner' => new ScalarCell($entry['owner']),
                'group' => new ScalarCell($entry['group']),
                'key' => $canEdit
                    ? new LinkCell($entry['key'], '#', $modalUrl, 'large')
                    : new ScalarCell($entry['key']),
                'source' => new ScalarCell($this->shorten($entry['source'])),
                'effective' => new ScalarCell($this->shorten($entry['effective'])),
                'status' => new StatusCell(
                    $statusLabels[$this->status($entry)],
                    $entry['overridden'] ? StatusVariant::Success : StatusVariant::Muted,
                ),
            ],
            actions: $actions,
        );
    }

    /**
     * Sestavuje autorizovanou hromadnou obnovu vybranych prekladu
     *
     * @return list<DataGridBulkActionDefinition>
     */
    private function bulkActions(): array
    {
        if (!$this->authorization->hasPermission('system.translations.edit')) {
            return [];
        }

        return [new DataGridBulkActionDefinition(
            label: $this->translator->get('translations.actions.bulk_reset'),
            icon: AdminIcon::ArrowCounterclockwise,
            endpoint: $this->urls->route(
                name: 'admin.system.module.ajax.create',
                params: ['module' => 'translations'],
            ),
            action: 'bulk-reset',
            allowedViews: ['all', 'default', 'overridden', 'missing'],
            confirmation: new ConfirmationDefinition('translations.confirm.bulk_reset'),
        )];
    }

    /**
     * Nacita lokalizovane statusy jednou pro definici nebo stranku gridu
     *
     * @return array{default:string,overridden:string,missing:string}
     */
    private function statusLabels(): array
    {
        $translations = $this->translator->group('translations');

        return [
            'default' => $translations['status.default'] ?? 'translations.status.default',
            'overridden' => $translations['status.overridden'] ?? 'translations.status.overridden',
            'missing' => $translations['status.missing'] ?? 'translations.status.missing',
        ];
    }

    /**
     * @param array{id:int,locale:string,owner:string,group:string,key:string,source:string,effective:string,overrideValue:string|null,overridden:bool,missingSource:bool} $entry
     */
    private function status(array $entry): string
    {
        if ($entry['overridden']) {
            return 'overridden';
        }

        return $entry['missingSource'] ? 'missing' : 'default';
    }

    /**
     * Zkracuje dlouhy text pouze pro prehledovou bunku
     */
    private function shorten(string $value): string
    {
        return mb_strimwidth($value, 0, 120, '…');
    }
}
