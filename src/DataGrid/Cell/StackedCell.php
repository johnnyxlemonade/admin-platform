<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

/**
 * Zobrazuje hlavni a doplnkovy text na samostatnych radcich bunky
 */
final readonly class StackedCell implements DataGridCell
{
    /**
     * Vytvori bunku s hlavnim a doplnkovym textem
     */
    public function __construct(
        private string $value,
        private string $secondary,
    ) {}

    /**
     * Vrati hlavni text bunky
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vrati doplnkovy text bunky
     */
    public function secondary(): string
    {
        return $this->secondary;
    }
}
