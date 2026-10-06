<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use Lemonade\Admin\Icon\AdminIcon;

/**
 * Popisuje hlavni akci zobrazenou nad datovou tabulkou
 */
final readonly class DataGridPrimaryAction
{
    /**
     * Nastavuje text, ikonu a cil hlavni akce datove tabulky
     */
    public function __construct(
        private string $translationKey,
        private AdminIcon $icon,
        private string $href,
        private ?string $modalUrl = null,
        private ?string $modalSize = null,
    ) {}

    /**
     * Vraci prekladovy klic popisku akce
     */
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Vraci ikonu akce
     */
    public function icon(): AdminIcon
    {
        return $this->icon;
    }

    /**
     * Vraci cilovy odkaz akce
     */
    public function href(): string
    {
        return $this->href;
    }

    /**
     * Vraci URL modalniho editoru, pokud jej akce otevre
     */
    public function modalUrl(): ?string
    {
        return $this->modalUrl;
    }

    /**
     * Vraci velikost modalniho editoru, pokud je urcena
     */
    public function modalSize(): ?string
    {
        return $this->modalSize;
    }
}
