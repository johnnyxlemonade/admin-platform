<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

/**
 * Zobrazuje jednoduchou hodnotu bunky
 */
final readonly class ScalarCell implements DataGridCell
{
    /**
     * Vytvori skalarni hodnotu bunky
     */
    public function __construct(private string|int $value) {}

    /**
     * Vrati skalarni hodnotu bunky
     */
    public function value(): string|int
    {
        return $this->value;
    }
}
