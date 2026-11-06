<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Framework\Database\QueryBuilder;

/**
 * Pripojuje jednoznacnou relacni projekci pred spolecnym CMS strankovanim
 */
interface CmsDataGridProjection
{
    /**
     * Rozsiri dotaz bez zmeny poctu radku jednoho CMS zaznamu
     */
    public function apply(QueryBuilder $builder, DataGridQuery $query): QueryBuilder;

    /**
     * Vrati explicitni whitelist SQL sloupcu razeni
     *
     * @return array<string,string>
     */
    public function sorts(): array;
}
