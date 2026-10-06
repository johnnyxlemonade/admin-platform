<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Predstavuje jednu odkazovou polozku navigace
 */
final class AdminNavigationItem implements AdminNavigationEntryInterface
{
    /**
     * Nastavuje hodnoty potrebne pro zobrazeni navigace
     * @param array<string, bool|float|int|string|null> $routeParameters
     */
    public function __construct(
        private readonly string $key,
        private readonly int $order,
        private readonly string $label,
        private readonly string $labelKey,
        private readonly string $route,
        private readonly array $routeParameters,
        private readonly bool $routePrefix,
        private readonly ?AdminIcon $icon,
        private readonly ?string $moduleCode,
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
     * Vraci pojmenovanou routu cilove stranky
     */
    public function route(): string
    {
        return $this->route;
    }

    /**
     * Vraci parametry predane cilove rou te
     * @return array<string, bool|float|int|string|null>
     */
    public function routeParameters(): array
    {
        return $this->routeParameters;
    }

    /**
     * Rozhoduje, zda odkaz pouziva prefix routy modulu
     */
    public function routePrefix(): bool
    {
        return $this->routePrefix;
    }

    /**
     * Vraci ikonu pouzitou v navigaci
     */
    public function icon(): ?AdminIcon
    {
        return $this->icon;
    }

    /**
     * Vraci kod modulu vlastniciho polozku
     */
    public function moduleCode(): ?string
    {
        return $this->moduleCode;
    }
}
