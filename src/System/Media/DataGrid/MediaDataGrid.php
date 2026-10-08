<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\DataGrid;

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
use Lemonade\Admin\Modules\State\ModuleStateResolver;
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
final class MediaDataGrid implements DataGridProviderInterface
{
    public function __construct(
        private readonly MediaService $media,
        private readonly ModuleStateResolver $modules,
        private readonly AdminThumbnailComponent $thumbnails,
        private readonly TranslatorInterface $translator,
        private readonly UrlGenerator $urls,
    ) {}

    public function permission(): string
    {
        return 'system.media.view';
    }

    /**
     * Deklaruje type views, module filtr a read-only katalogove sloupce
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
        foreach ($this->modules->all() as $state) {
            if ($state->available()) {
                $modules[$state->code()] = new SelectOptionDefinition(
                    value: $state->code(),
                    label: $state->code(),
                );
            }
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
                url: $kind === 'image'
                    ? $this->urls->route(name: 'admin.media.download', params: ['file' => $id])
                    : null,
                download: $kind === 'image',
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
        ]);
    }
}
