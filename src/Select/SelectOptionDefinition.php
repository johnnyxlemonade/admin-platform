<?php

declare(strict_types=1);

namespace Lemonade\Admin\Select;

use InvalidArgumentException;

/**
 * Popisuje hodnotu a popisek jedne volby vyberu
 */
final readonly class SelectOptionDefinition
{
    /**
     * Nastavuje neprazdnou hodnotu a popisek volby
     */
    public function __construct(private string $value, private string $label)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Select option value must not be empty.');
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException('Select option label must not be empty.');
        }
    }

    /**
     * Vraci hodnotu odesilanou pro zvolenou volbu
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vraci popisek zobrazeny uzivateli ve vyberu
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Prevadi volbu do datoveho tvaru pro klienta
     *
     * @return array{value:string,label:string}
     */
    public function toArray(): array
    {
        return ['value' => $this->value, 'label' => $this->label];
    }
}
