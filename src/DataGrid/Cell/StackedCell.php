<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use InvalidArgumentException;

/**
 * Zobrazuje hlavni a doplnkovy text na samostatnych radcich bunky
 */
final readonly class StackedCell implements DataGridCell
{
    /**
     * Vytvori bunku s hlavnim, doplnkovym textem a volitelnym odkazem
     */
    public function __construct(
        private string $value,
        private string $secondary,
        private ?string $url = null,
    ) {
        if ($url === '') {
            throw new InvalidArgumentException('DataGrid stacked URL must not be empty.');
        }
    }

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

    /**
     * Vrati volitelnou adresu primarniho textu
     */
    public function url(): ?string
    {
        return $this->url;
    }
}
