<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

/**
 * Nastavuje zobrazeni a razeni sloupce datove tabulky
 */
final readonly class DataGridColumnDefinition
{
    /**
     * Vytvori sloupec s prekladem a volitelnym razenim
     */
    public function __construct(private string $key, private string $translationKey, private ?string $sortKey = null, private ?string $class = null) {}

    /**
     * Vrati klic sloupce
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Vrati prekladovy klic nazvu sloupce
     */
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Vrati volitelny klic razeni
     */
    public function sortKey(): ?string
    {
        return $this->sortKey;
    }

    /**
     * Vrati volitelnou CSS tridu sloupce
     */
    public function class(): ?string
    {
        return $this->class;
    }
}
