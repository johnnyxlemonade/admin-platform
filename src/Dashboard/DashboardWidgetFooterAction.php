<?php

declare(strict_types=1);

namespace Lemonade\Admin\Dashboard;

/**
 * Nastavuje akci zobrazovanou v zapati widgetu
 */
final readonly class DashboardWidgetFooterAction
{
    /**
     * Vytvori akci s prekladovym klicem a cilovou routou
     *
     * @param array<string, bool|float|int|string|null> $routeParameters
     */
    public function __construct(
        private string $labelKey,
        private string $routeName,
        private array $routeParameters = [],
    ) {}

    /**
     * Vrati prekladovy klic popisku akce
     */
    public function labelKey(): string
    {
        return $this->labelKey;
    }

    /**
     * Vrati nazev cilove routy
     */
    public function routeName(): string
    {
        return $this->routeName;
    }

    /**
     * Vrati parametry cilove routy
     *
     * @return array<string, bool|float|int|string|null>
     */
    public function routeParameters(): array
    {
        return $this->routeParameters;
    }
}
