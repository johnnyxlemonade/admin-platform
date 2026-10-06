<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Predstavuje skupinu serazenych polozek navigace
 */
final class AdminNavigationGroup implements AdminNavigationEntryInterface
{
    /**
     * Nastavuje hodnoty potrebne pro zobrazeni navigace
     * @param list<AdminNavigationItem> $items
     */
    public function __construct(
        private readonly string $key,
        private readonly int $order,
        private readonly string $code,
        private readonly string $label,
        private readonly string $labelKey,
        private readonly AdminIcon $icon,
        private readonly array $items,
    ) {}

    /**
     * Vraci stabilni klic polozky navigace
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Vraci poradi polozky nebo skupiny navigace
     */
    public function order(): int
    {
        return $this->order;
    }

    /**
     * Vraci kod skupiny navigace
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Vraci zobrazeny popisek navigace
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Vraci lokalizacni klic popisku navigace
     */
    public function labelKey(): string
    {
        return $this->labelKey;
    }

    /**
     * Vraci ikonu pouzitou v navigaci
     */
    public function icon(): AdminIcon
    {
        return $this->icon;
    }

    /**
     * Vraci serazene a autorizovane polozky navigace
     * @return list<AdminNavigationItem>
     */
    public function items(): array
    {
        return $this->items;
    }
}
