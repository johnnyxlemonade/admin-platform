<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Popisuje navigacni a routovaci udaje administracniho modulu
 */
final readonly class AdminModuleMetadata
{
    /**
     * Nastavuje zavislosti potrebne pro praci s administracnimi moduly
     */
    public function __construct(
        private string $nameKey,
        private AdminIcon $icon,
        private ?string $navigationGroup,
        private int $navigationOrder,
        private string $destinationRoute,
        private string $routeSegment,
        /** @var array<string, scalar|null> */
        private array $destinationParameters = [],
        private ?string $navigationPermission = null,
        private bool $superAdminOnly = false,
    ) {}

    /**
     * Vraci hodnotu namekey z metadat modulu
     */
    public function nameKey(): string
    {
        return $this->nameKey;
    }

    /**
     * Vraci hodnotu icon z metadat modulu
     */
    public function icon(): AdminIcon
    {
        return $this->icon;
    }

    /**
     * Vraci hodnotu navigationgroup z metadat modulu
     */
    public function navigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    /**
     * Rozhoduje stav isstandalonenavigation
     */
    public function isStandaloneNavigation(): bool
    {
        return $this->navigationGroup === null;
    }

    /**
     * Vraci hodnotu navigationorder z metadat modulu
     */
    public function navigationOrder(): int
    {
        return $this->navigationOrder;
    }

    /**
     * Vraci hodnotu destinationroute z metadat modulu
     */
    public function destinationRoute(): string
    {
        return $this->destinationRoute;
    }

    /**
     * Vraci hodnotu routesegment z metadat modulu
     */
    public function routeSegment(): string
    {
        return $this->routeSegment;
    }

    /**
     * Vraci hodnotu destinationparameters z metadat modulu
     * @return array<string, scalar|null>
     */
    public function destinationParameters(): array
    {
        return $this->destinationParameters;
    }

    /**
     * Vraci hodnotu navigationpermission z metadat modulu
     */
    public function navigationPermission(): ?string
    {
        return $this->navigationPermission;
    }

    /**
     * Vraci hodnotu superadminonly z metadat modulu
     */
    public function superAdminOnly(): bool
    {
        return $this->superAdminOnly;
    }
}
