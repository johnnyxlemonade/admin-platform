<?php

declare(strict_types=1);

namespace Lemonade\Admin\Page;

/**
 * Popisuje data administracni stranky modulu
 */
final readonly class ModulePage
{
    /**
     * Nastavuje hodnoty administracni stranky
     * @param array<string, mixed> $data
     */
    public function __construct(
        private string $view,
        private string $title,
        private array $data,
    ) {}

    /**
     * Vraci sablonu administracni stranky
     */
    public function view(): string
    {
        return $this->view;
    }

    /**
     * Vraci nadpis administracni stranky
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Vraci data predana sablone stranky
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
