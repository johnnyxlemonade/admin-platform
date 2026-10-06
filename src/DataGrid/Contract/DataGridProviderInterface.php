<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Contract;

use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;

/**
 * Definuje zdroj dat pro datovou tabulku
 */
interface DataGridProviderInterface
{
    /**
     * Vrati definici datove tabulky
     */
    public function dataGridDefinition(): DataGridDefinition;

    /**
     * Vrati opravneni potrebne pro datovou tabulku
     */
    public function permission(): string;

    /**
     * Provede dotaz datove tabulky
     */
    public function execute(DataGridQuery $query): DataGridResult;
}
