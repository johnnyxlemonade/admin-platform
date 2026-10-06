<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use Lemonade\Admin\Flag\AdminCountryFlag;

/**
 * Zobrazuje hodnotu doplnenou o vlajku zeme
 */
final readonly class FlagCell implements DataGridCell
{
    /**
     * Vytvori bunku s hodnotou a vlajkou zeme
     */
    public function __construct(
        private string $value,
        string $flagCode,
    ) {
        $this->flag = AdminCountryFlag::from($flagCode)->unicode();
    }

    private string $flag;

    /**
     * Vrati textovou hodnotu bunky
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Vrati Unicode symbol vlajky
     */
    public function flag(): string
    {
        return $this->flag;
    }
}
