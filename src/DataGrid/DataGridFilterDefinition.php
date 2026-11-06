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
    public function __construct(
        private string $key,
        private StaticSelectOptionSource $optionSource,
        private ?string $label = null,
        private ?string $allLabelKey = null,
        private ?string $inputType = null,
        private ?string $valuePattern = null,
    ) {}

    /**
     * Overi statickou volbu nebo explicitne omezenou skalarni hodnotu filtru
     */
    public function accepts(string $value): bool
    {
        if ($this->inputType === null) {
            return in_array($value, $this->allowedValues(), true);
        }
        return $this->valuePattern !== null && strlen($value) <= 255 && preg_match($this->valuePattern, $value) === 1;
    }

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

    /**
     * Vraci vlastni nazev filtru bez prekladu
     */
    public function label(): ?string
    {
        return $this->label;
    }

    /**
     * Vraci prekladovy klic prazdne volby filtru
     */
    public function allLabelKey(): ?string
    {
        return $this->allLabelKey;
    }

    /**
     * Vraci typ vstupu skalarniho filtru
     */
    public function inputType(): ?string
    {
        return $this->inputType;
    }
}
