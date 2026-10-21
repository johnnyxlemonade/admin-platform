<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Modules\DataGrid;

use Lemonade\Admin\DataGrid\Cell\ScalarCell;
use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Modules\Catalog\AvailableModule;
use Lemonade\Admin\Modules\Catalog\ModuleCatalog;
use Lemonade\Framework\Localization\TranslatorInterface;

/**
 * Promita Composer katalog do read-only prehledu modulu
 */
final readonly class ModulesDataGrid implements DataGridProviderInterface
{
    /**
     * Nastavuje Composer katalog a lokalizaci nazvu modulu
     */
    public function __construct(
        private ModuleCatalog $modules,
        private TranslatorInterface $translator,
    ) {}

    /**
     * Urci pravo vyzadovane shared DataGrid transportem
     */
    public function permission(): string
    {
        return 'system.modules.view';
    }

    /**
     * Deklaruje sloupce read-only prehledu Composer modulu
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
                    key: 'version',
                    translationKey: 'modules.fields.version',
                    sortKey: 'version',
                ),
                new DataGridColumnDefinition(
                    key: 'description',
                    translationKey: 'modules.fields.description',
                ),
            ],
            searchEnabled: true,
            filters: [],
            defaultSortKey: 'name',
            defaultSortDirection: 'asc',
            defaultPageSize: 25,
            maximumPageSize: 100,
            defaultView: 'all',
            showAllView: true,
            viewFilterKey: null,
        );
    }

    /**
     * Filtruje, radi a strankuje moduly dostupne v Composer instalaci
     */
    public function execute(DataGridQuery $query): DataGridResult
    {
        $modules = array_values(array_filter(
            $this->modules->all(),
            fn(AvailableModule $module): bool => $this->matches($module, $query->search()),
        ));
        usort($modules, fn(AvailableModule $left, AvailableModule $right): int => $this->compare($left, $right, $query));

        $total = count($modules);
        $pageItems = array_slice($modules, ($query->page() - 1) * $query->pageSize(), $query->pageSize());

        return new DataGridResult(
            items: array_map(fn(AvailableModule $module): DataGridRowDefinition => $this->row($module), $pageItems),
            page: $query->page(),
            perPage: $query->pageSize(),
            total: $total,
        );
    }

    /**
     * Vyhleda text v nazvu, kodu, verzi, popisu nebo nazvu balicku
     */
    private function matches(AvailableModule $module, string $search): bool
    {
        $term = strtolower($search);
        if ($term === '') {
            return true;
        }

        foreach ([$this->label($module), $module->code(), $module->version(), $module->description(), $module->packageName()] as $value) {
            if (str_contains(strtolower($value), $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Radi podle zvolene hodnoty a kodu jako stabilniho tie-breakeru
     */
    private function compare(AvailableModule $left, AvailableModule $right, DataGridQuery $query): int
    {
        $leftValue = match ($query->sortKey()) {
            'code' => $left->code(),
            'version' => $left->version(),
            default => $this->label($left),
        };
        $rightValue = match ($query->sortKey()) {
            'code' => $right->code(),
            'version' => $right->version(),
            default => $this->label($right),
        };

        $comparison = ($query->sortDirection() === 'desc' ? -1 : 1) * strcmp($leftValue, $rightValue);

        return $comparison !== 0 ? $comparison : strcmp($left->code(), $right->code());
    }

    /**
     * Sklada read-only radek bez lifecycle akci
     */
    private function row(AvailableModule $module): DataGridRowDefinition
    {
        return new DataGridRowDefinition(
            id: abs(crc32($module->code())),
            cells: [
                'name' => new ScalarCell(value: $this->label($module)),
                'code' => new ScalarCell(value: $module->code()),
                'version' => new ScalarCell(value: $module->version()),
                'description' => new ScalarCell(value: $module->description() !== '' ? $module->description() : '—'),
            ],
        );
    }

    /**
     * Lokalizuje nazev deklarovany manifestem
     */
    private function label(AvailableModule $module): string
    {
        return $this->translator->get($module->manifest()->labelKey());
    }
}
