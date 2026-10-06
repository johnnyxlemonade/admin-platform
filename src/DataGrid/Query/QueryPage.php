<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Query;

use InvalidArgumentException;

/**
 * Nese jednu stranku vysledku dotazu
 *
 * @template TRow
 */
final readonly class QueryPage
{
    /**
     * Vytvori strankovany vysledek generickeho dotazu
     *
     * @param list<TRow> $items
     */
    public function __construct(
        private array $items,
        private int $page,
        private int $perPage,
        private int $total,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be at least 1.');
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException('Per-page value must be at least 1.');
        }
        if ($total < 0) {
            throw new InvalidArgumentException('Total must not be negative.');
        }
    }

    /**
     * Vrati radky aktualni stranky
     *
     * @return list<TRow>
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
