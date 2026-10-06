<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use Lemonade\Admin\Select\StaticSelectOptionSource;

/**
 * Nastavuje filtr datove tabulky a jeho povolene hodnoty
 */
final readonly class DataGridFilterDefinition
{
    /**
     * Vytvori filtr s pevne danymi volbami
     */
    public function __construct(private string $key, private StaticSelectOptionSource $optionSource) {}

    /**
     * Vrati klic filtru
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Vrati klic filtru
     */
    /**
     * Vrati hodnoty povolene filtrem
     *
     * @return list<string>
     */
    public function allowedValues(): array
    {
        return $this->optionSource->values();
    }

    /**
     * Vrati zdroj voleb filtru
     */
    public function optionSource(): StaticSelectOptionSource
    {
        return $this->optionSource;
    }

    /**
     * Vrati volby filtru pro klientsky transport
     *
     * @return list<array{value:string,label:string}>
     */
    public function options(): array
    {
        return array_map(static fn(\Lemonade\Admin\Select\SelectOptionDefinition $option): array => $option->toArray(), $this->optionSource->options());
    }
}
