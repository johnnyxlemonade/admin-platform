<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Media\Services;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use Lemonade\Admin\Presentation\Models\AdminFileModel;

/**
 * Poskytuje read-only dotazy nad canonical katalogem system_file
 */
final class MediaService
{
    public function __construct(
        private readonly AdminFileModel $files,
    ) {}

    /**
     * Vraci jednu stranku katalogu z databazoveho zdroje pravdy
     *
     * @return QueryPage<array<string,mixed>>
     */
    public function listForDataGrid(DataGridQuery $query): QueryPage
    {
        return $this->files->listForManagement($query);
    }

    /**
     * Vraci hodnoty modulu a usage z canonical katalogu pro povolene filtry
     *
     * @return list<array{module_code:string,usage:string}>
     */
    public function filterValues(): array
    {
        return $this->files->managementFilterValues();
    }
}
