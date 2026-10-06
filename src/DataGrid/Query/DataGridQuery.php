<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Query;

/**
 * Nastavuje strankovani, razeni a filtry dotazu datove tabulky
 */
final readonly class DataGridQuery
{
    /**
     * Vytvori dotaz s overenym strankovanim, razenim a filtry
     *
     * @param array<string, string> $filters
     */
    public function __construct(
        private int $page,
        private int $pageSize,
        private string $sortKey,
        private string $sortDirection,
        private string $search,
        private array $filters,
    ) {}

    /**
     * Vrati cislo pozadovane stranky
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Vrati pocet radku na stranku
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Vrati klic razeni
     */
    public function sortKey(): string
    {
        return $this->sortKey;
    }

    /**
     * Vrati smer razeni
     */
    public function sortDirection(): string
    {
        return $this->sortDirection;
    }

    /**
     * Vrati hledany text
     */
    public function search(): string
    {
        return $this->search;
    }

    /**
     * Vrati aktivni filtry dotazu
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * Vrati hodnotu filtru podle klice
     */
    public function filter(string $key): ?string
    {
        return $this->filters[$key] ?? null;
    }
}
