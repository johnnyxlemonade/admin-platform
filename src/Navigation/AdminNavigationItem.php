<?php

declare(strict_types=1);

namespace Lemonade\Admin\Navigation;

use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\Module\AdminModuleMetadata;
use Lemonade\Admin\Module\AdminNavigationGroup;

/**
 * Predstavuje jednu odkazovou polozku navigace
 */
final readonly class AdminNavigationItem implements AdminNavigationEntryInterface
{
    /**
     * Nastavuje hodnoty potrebne pro zobrazeni navigace
     * @param array<string, bool|float|int|string|null> $routeParameters
     */
    public function __construct(
        private string $key,
        private int $order,
        private string $label,
        private ?string $labelKey,
        private string $route,
        private array $routeParameters,
        private bool $routePrefix,
        private ?AdminIcon $icon,
        private ?string $moduleCode,
        private ?AdminNavigationGroup $moduleNavigationGroup = null,
        private ?string $moduleRouteSegment = null,
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
    public function labelKey(): ?string
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

    /**
     * Rozhoduje, zda polozka patri aktualne otevrenemu standardnimu modulu
     */
    public function isActiveFor(?AdminModuleMetadata $metadata): bool
    {
        return $metadata !== null
            && $this->moduleNavigationGroup === $metadata->navigationGroup()
            && $this->moduleRouteSegment === $metadata->routeSegment();
    }
}
