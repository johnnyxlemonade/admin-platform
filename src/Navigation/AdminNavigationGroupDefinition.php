<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Popisuje kod, nazev, poradi a ikonu skupiny navigace
 */
final readonly class AdminNavigationGroupDefinition
{
    /**
     * Nastavuje hodnoty potrebne pro zobrazeni navigace
     */
    public function __construct(
        private string $code,
        private string $nameKey,
        private int $order,
        private AdminIcon $icon,
    ) {}

    /**
     * Vraci kod skupiny navigace
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Vraci lokalizacni klic nazvu skupiny
     */
    public function nameKey(): string
    {
        return $this->nameKey;
    }

    /**
     * Vraci poradi polozky nebo skupiny navigace
     */
    public function order(): int
    {
        return $this->order;
    }

    /**
     * Vraci ikonu pouzitou v navigaci
     */
    public function icon(): AdminIcon
    {
        return $this->icon;
    }
}
