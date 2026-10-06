<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

/**
 * Poskytuje strankovany vysledek dotazu datove tabulky
 */
final readonly class DataGridResult
{
    /**
     * Vytvori strankovany vysledek s radky a poctem zaznamu
     *
     * @param list<DataGridRowDefinition> $items
     */
    public function __construct(
        private array $items,
        private int $page,
        private int $perPage,
        private int $total,
    ) {}

    /**
     * Vrati radky aktualni stranky
     *
     * @return list<DataGridRowDefinition>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Vrati cislo aktualni stranky
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * Vrati pocet radku na stranku
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Vrati celkovy pocet zaznamu
     */
    public function total(): int
    {
        return $this->total;
    }
}
