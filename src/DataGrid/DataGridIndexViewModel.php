<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

/**
 * Poskytuje data pro zobrazeni datove tabulky modulu
 */
final readonly class DataGridIndexViewModel
{
    /**
     * Vytvori data stranky gridu s texty a volitelnou primarni akci
     */
    public function __construct(
        private string $moduleCode,
        private string $title,
        private ?string $description,
        private string $endpoint,
        private DataGridDefinition $definition,
        private ?DataGridPrimaryAction $primaryAction,
        private string $loadingText,
        private string $emptyText,
        private string $errorText,
        private ?string $gridClass = null,
    ) {}

    /**
     * Vrati kod modulu gridu
     */
    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    /**
     * Vrati nadpis stranky gridu
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Vrati volitelny popis stranky gridu
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Vrati endpoint DataGrid transportu
     */
    public function endpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Vrati definici gridu
     */
    public function definition(): DataGridDefinition
    {
        return $this->definition;
    }

    /**
     * Vrati volitelnou primarni akci stranky
     */
    public function primaryAction(): ?DataGridPrimaryAction
    {
        return $this->primaryAction;
    }

    /**
     * Vrati text nacitani gridu
     */
    public function loadingText(): string
    {
        return $this->loadingText;
    }

    /**
     * Vrati text prazdneho gridu
     */
    public function emptyText(): string
    {
        return $this->emptyText;
    }

    /**
     * Vrati text chyby gridu
     */
    public function errorText(): string
    {
        return $this->errorText;
    }

    /**
     * Vrati volitelnou CSS tridu gridu
     */
    public function gridClass(): ?string
    {
        return $this->gridClass;
    }
}
