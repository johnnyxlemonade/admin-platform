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
        public AdminNavigationGroup $navigationGroup,
        private int $navigationOrder,
        private string $routeSegment,
        private ?string $navigationPermission = null,
        private bool $superAdminOnly = false,
        private ?string $descriptionKey = null,
    ) {}

    /**
     * Vraci hodnotu namekey z metadat modulu
     */
    public function nameKey(): string
    {
        return $this->nameKey;
    }

    /**
     * Vraci lokalizacni klic kratkeho popisu modulu
     */
    public function descriptionKey(): ?string
    {
        return $this->descriptionKey;
    }

    /**
     * Vraci hodnotu icon z metadat modulu
     */
    public function icon(): AdminIcon
    {
        return $this->icon;
    }

    /**
     * Vraci hodnotu navigationorder z metadat modulu
     */
    public function navigationOrder(): int
    {
        return $this->navigationOrder;
    }

    /**
     * Vraci hodnotu routesegment z metadat modulu
     */
    public function routeSegment(): string
    {
        return $this->routeSegment;
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
