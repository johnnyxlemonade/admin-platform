<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use InvalidArgumentException;

/**
 * Zobrazuje prelozeny text v bunce
 */
final readonly class TranslationCell implements DataGridCell
{
    /**
     * Vytvori bunku s prekladovym klicem a zalohovanou hodnotou
     */
    public function __construct(private string $translationKey, private string $value)
    {
        if ($translationKey === '') {
            throw new InvalidArgumentException('DataGrid translation key must not be empty.');
        }
    }

    /**
     * Vrati prekladovy klic bunky
     */
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Vrati zalohovanou hodnotu bunky
     */
    public function value(): string
    {
        return $this->value;
    }
}
