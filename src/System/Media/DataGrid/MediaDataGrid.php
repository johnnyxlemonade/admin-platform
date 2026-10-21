<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\DataGrid;

use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionKind;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionPlacement;
use Lemonade\Admin\DataGrid\Cell\DateTimeCell;
use Lemonade\Admin\DataGrid\Cell\StackedCell;
use Lemonade\Admin\DataGrid\Cell\StatusCell;
use Lemonade\Admin\DataGrid\Cell\StatusVariant;
use Lemonade\Admin\DataGrid\Cell\ThumbnailCell;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridFilterDefinition;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Admin\Presentation\AdminThumbnailComponent;
use Lemonade\Admin\Presentation\MediaCategory;
use Lemonade\Admin\Select\SelectOptionDefinition;
use Lemonade\Admin\Select\StaticSelectOptionSource;
use Lemonade\Admin\System\Media\Services\MediaService;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Promita databaseovy katalog system_file do DataGridu bez per-row lookupu
 */
final readonly class MediaDataGrid implements DataGridProviderInterface
{
    public function __construct(
        private MediaService $media,
        private ModuleCatalog $modules,
        private AdminThumbnailComponent $thumbnails,
        private AuthorizationService $authorization,
        private TranslatorInterface $translator,
        private UrlGenerator $urls,
    ) {}

    public function permission(): string
    {
        return 'system.media.view';
    }

    /**
     * Deklaruje type views, module filtr, katalogove sloupce a dostupne row actions
     */
    public function dataGridDefinition(): DataGridDefinition
    {
        $filterOptions = $this->filterOptions();

        return new DataGridDefinition(
            id: 'media',
            columns: [
                new DataGridColumnDefinition(key: 'file', translationKey: 'media.fields.file', sortKey: 'name'),
                new DataGridColumnDefinition(
                    key: 'type',
                    translationKey: 'media.fields.type',
                    sortKey: 'kind',
                    class: 'd-none d-md-table-cell',
                ),
                new DataGridColumnDefinition(
                    key: 'sizeDimensions',
                    translationKey: 'media.fields.size_dimensions',
                    sortKey: 'size',
                    class: 'd-none d-lg-table-cell',
                ),
                new DataGridColumnDefinition(
                    key: 'ownerUsage',
                    translationKey: 'media.fields.owner_usage',
                    sortKey: 'module',
                    class: 'd-none d-xl-table-cell',
                ),
                new DataGridColumnDefinition(
                    key: 'createdAt',
                    translationKey: 'media.fields.created_at',
                    sortKey: 'createdAt',
                    class: 'd-none d-lg-table-cell',
                ),
                new DataGridColumnDefinition(key: 'actions', translationKey: 'admin.common.actions'),
            ],
            searchEnabled: true,
            filters: [
                new DataGridFilterDefinition(key: 'module', optionSource: new StaticSelectOptionSource(options: $filterOptions)),
                new DataGridFilterDefinition(key: 'type', optionSource: new StaticSelectOptionSource(options: $this->typeOptions())),
            ],
            defaultSortKey: 'createdAt',
            defaultSortDirection: 'desc',
            defaultPageSize: 25,
            maximumPageSize: 100,
            defaultView: 'all',
            showAllView: true,
            viewFilterKey: 'type',
        );
    }

    /**
     * Sklada radky z jedine paginovane databaseove projekce
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $list = $this->media->listForDataGrid($query);

        return new DataGridResult(items: array_map(fn(array $file): DataGridRowDefinition => $this->row($file), $list->items()), page: $list->page(), perPage: $list->perPage(), total: $list->total());
    }

    /**
     * @return list<SelectOptionDefinition>
     */
    private function typeOptions(): array
    {
        return array_map(fn(string $type): SelectOptionDefinition => new SelectOptionDefinition(value: $type, label: $this->translator->get('media.list.' . $type)), ['image', 'document', 'video', 'other']);
    }

    /**
     * Vytvari stabilni module filter options z runtime katalogu a existujicich zaznamu
     *
     * @return list<SelectOptionDefinition>
     */
    private function filterOptions(): array
    {
        $modules = [];
        foreach ($this->modules->all() as $module) {
            $modules[$module->code()] = new SelectOptionDefinition(
                value: $module->code(),
                label: $module->code(),
            );
        }
        foreach ($this->media->filterValues() as $value) {
            $modules[$value['module_code']] = new SelectOptionDefinition(value: $value['module_code'], label: $value['module_code']);
        }

        ksort($modules);

        return array_values($modules);
    }

    /**
     * Promita jeden prednacteny katalogovy radek bez dalsi persistence operace
     *
     * @param array<string,mixed> $file
     */
    private function row(array $file): DataGridRowDefinition
    {
        $id = (int) $file['id'];
        $kind = (string) $file['kind'];
        $type = MediaCategory::tryFrom((string) $file['media_category']) ?? MediaCategory::Other;
        $displayName = trim((string) ($file['display_name'] ?? ''));
        $originalFilename = (string) $file['original_filename'];
        $name = $displayName === '' ? $originalFilename : $displayName;
        $fallback = strtoupper(substr((string) $file['extension'], 0, 4));
        $dimensions = $kind === 'image' && $file['width'] !== null && $file['height'] !== null
            ? $file['width'] . ' × ' . $file['height']
            : '—';

        return new DataGridRowDefinition(id: $id, cells: [
            'file' => new ThumbnailCell(
                value: $name,
                thumbnail: $kind === 'image'
                    ? $this->thumbnails->image(
                        module: (string) $file['module_code'],
                        imageId: (string) $id,
                        alt: $name,
                        fallback: $fallback,
                        presentation: 'datagrid',
                    )
                    : $this->thumbnails->fallback(fallback: $fallback),
                secondary: $displayName !== '' && $displayName !== $originalFilename ? $originalFilename : '',
            ),
            'type' => new StatusCell(
                value: $this->translator->get('media.list.' . $type->value),
                variant: StatusVariant::Muted,
            ),
            'sizeDimensions' => new StackedCell(
                value: human_filesize((int) $file['file_size'], 1),
                secondary: $dimensions,
            ),
            'ownerUsage' => new StackedCell(
                value: (string) $file['module_code'] . ' #' . $file['entity_id'],
                secondary: (string) $file['usage'],
            ),
            'createdAt' => new DateTimeCell(value: (string) $file['created_at']),
        ], actions: $this->actions($id));
    }

    /**
     * Sklada download pro kazdeho ctenare a modalni upravu pouze pro Media editora
     *
     * @return list<DataGridRowActionDefinition>
     */
    private function actions(int $id): array
    {
        $actions = [
            (new DataGridRowActionDefinition(
                key: 'download',
                label: $this->translator->get('admin.common.download'),
                url: $this->urls->route(name: 'admin.media.download', params: ['file' => $id]),
                method: 'GET',
                kind: DataGridRowActionKind::Navigate,
                placement: DataGridRowActionPlacement::Secondary,
            ))->withIcon(AdminIcon::Export),
        ];
        if ($this->authorization->hasPermission('system.media.edit')) {
            $actions[] = (new DataGridRowActionDefinition(
                key: 'edit',
                label: $this->translator->get('admin.common.edit'),
                url: '#',
                method: 'GET',
                modalUrl: $this->urls->route(name: 'admin.media.presentation.modal', params: ['file' => $id]),
                modalSize: 'medium',
                kind: DataGridRowActionKind::Modal,
                placement: DataGridRowActionPlacement::Secondary,
                refresh: true,
            ))->withIcon(AdminIcon::PencilSquare);
        }

        return $actions;
    }
}
