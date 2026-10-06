<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

/**
 * Zobrazuje kodovou hodnotu bunky
 */
final readonly class CodeCell implements DataGridCell
{
    /**
     * Vytvori kodovou hodnotu bunky
     */
    public function __construct(private string $value) {}

    /**
     * Vrati kodovou hodnotu bunky
     */
    public function value(): string
    {
        return $this->value;
    }
}
