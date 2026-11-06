<?php

declare(strict_types=1);

namespace Lemonade\Admin\Module;

use Lemonade\Admin\Authorization\PermissionSection;
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
        private AdminNavigationGroup $navigationGroup,
        private int $navigationOrder,
        private string $routeSegment,
        private ?string $navigationPermission = null,
        private bool $superAdminOnly = false,
        private ?string $descriptionKey = null,
        private ?string $name = null,
        private bool $navigationVisible = true,
        private ?PermissionSection $permissionSection = null,
        private ?string $description = null,
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

    /**
     * Vraci navigacni skupinu modulu
     */
    public function navigationGroup(): AdminNavigationGroup
    {
        return $this->navigationGroup;
    }

    /**
     * Vraci vlastni nazev modulu bez prekladu
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Urcuje viditelnost modulu v navigaci
     */
    public function navigationVisible(): bool
    {
        return $this->navigationVisible;
    }

    /**
     * Vraci sekci pro zobrazeni opravneni modulu
     */
    public function permissionSection(): ?PermissionSection
    {
        return $this->permissionSection;
    }

    /**
     * Vraci vlastni popis modulu bez prekladu
     */
    public function description(): ?string
    {
        return $this->description;
    }
}
