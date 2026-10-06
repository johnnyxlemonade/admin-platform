<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

/**
 * Popisuje jednu navigacni volbu kontextu v hlavicce editoru
 */
final readonly class AdminEditorHeaderContextItem
{
    /**
     * Nastavuje navigaci, aktivni stav a doplnkovy text volby
     */
    public function __construct(
        private string $label,
        private string $href,
        private bool $active = false,
        private ?string $secondary = null,
    ) {}

    /**
     * Vrati zobrazovany nazev volby
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Vrati interni cil navigace volby
     */
    public function href(): string
    {
        return $this->href;
    }

    /**
     * Urci aktivni volbu kontextu
     */
    public function active(): bool
    {
        return $this->active;
    }

    /**
     * Vrati volitelny doplnkovy stav volby
     */
    public function secondary(): ?string
    {
        return $this->secondary;
    }
}
