<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\Action\DataGridBulkActionDefinition;

/**
 * Nastavuje sloupce, filtry a vychozi chovani datove tabulky
 */
final readonly class DataGridDefinition
{
    /**
     * Vytvori definici gridu s jeho zobrazenim a pravidly dotazu
     *
     * @param list<DataGridColumnDefinition> $columns
     * @param list<DataGridFilterDefinition> $filters
     * @param list<DataGridBulkActionDefinition> $bulkActions
     */
    public function __construct(
        private string $id,
        private array $columns,
        private bool $searchEnabled,
        private array $filters,
        private string $defaultSortKey,
        private string $defaultSortDirection,
        private int $defaultPageSize,
        private int $maximumPageSize,
        private string $defaultView = 'all',
        private bool $showAllView = true,
        private ?string $viewFilterKey = 'status',
        private array $bulkActions = [],
    ) {
        $sortKeys = $this->sortKeys();
        if (count($sortKeys) !== count(array_filter(
            array_map(
                static fn(DataGridColumnDefinition $column): ?string => $column->sortKey(),
                $this->columns,
            ),
            static fn(?string $sortKey): bool => $sortKey !== null,
        ))) {
            throw new InvalidArgumentException('DataGrid sortable column sort keys must be unique.');
        }
        if (!in_array($this->defaultSortKey, $sortKeys, true)) {
            throw new InvalidArgumentException('DataGrid default sort key must be declared by a sortable column.');
        }
        if (!in_array($this->defaultSortDirection, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('DataGrid default sort direction must be asc or desc.');
        }
    }

    /**
     * Vrati identifikator gridu
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Vrati sloupce gridu
     *
     * @return list<DataGridColumnDefinition>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Urci zda grid podporuje hledani
     */
    public function searchEnabled(): bool
    {
        return $this->searchEnabled;
    }

    /**
     * Vrati filtry gridu
     *
     * @return list<DataGridFilterDefinition>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Vrati filtr podle klice pokud existuje
     */
    public function filter(string $key): ?DataGridFilterDefinition
    {
        foreach ($this->filters as $filter) {
            if ($filter->key() === $key) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * Vrati povolene klice razeni
     *
     * @return list<string>
     */
    public function sortKeys(): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn(DataGridColumnDefinition $column): ?string => $column->sortKey(),
                $this->columns,
            ),
            static fn(?string $sortKey): bool => $sortKey !== null,
        )));
    }

    /**
     * Vrati vychozi klic razeni
     */
    public function defaultSortKey(): string
    {
        return $this->defaultSortKey;
    }

    /**
     * Vrati vychozi smer razeni
     */
    public function defaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    /**
     * Vrati vychozi velikost stranky
     */
    public function defaultPageSize(): int
    {
        return $this->defaultPageSize;
    }

    /**
     * Vrati maximalni velikost stranky
     */
    public function maximumPageSize(): int
    {
        return $this->maximumPageSize;
    }

    /**
     * Vrati vychozi pohled gridu
     */
    public function defaultView(): string
    {
        return $this->defaultView;
    }

    /**
     * Urci zda grid zobrazuje pohled vsech zaznamu
     */
    public function showAllView(): bool
    {
        return $this->showAllView;
    }

    /**
     * Vrati filtr, jehoz hodnoty se zobrazuji jako canonical DataGrid views
     */
    public function viewFilter(): ?DataGridFilterDefinition
    {
        return $this->viewFilterKey === null ? null : $this->filter($this->viewFilterKey);
    }

    /**
     * Vrati hromadne akce povolene pro grid
     *
     * @return list<DataGridBulkActionDefinition>
     */
    public function bulkActions(): array
    {
        return $this->bulkActions;
    }

    /**
     * Urci zda grid vykresli vyber radku
     */
    public function selectionEnabled(): bool
    {
        return $this->bulkActions !== [];
    }
}
