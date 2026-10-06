<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

/**
 * Zobrazuje datum a cas v bunce
 */
final readonly class DateTimeCell implements DataGridCell
{
    /**
     * Vytvori datumovou hodnotu bunky
     */
    public function __construct(private string $value) {}

    /**
     * Vrati datumovou hodnotu bunky
     */
    public function value(): string
    {
        return $this->value;
    }
}
