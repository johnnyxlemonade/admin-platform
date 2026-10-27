<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

/**
 * Deklaruje projekci, razeni a hledani mapovane domenove vlastnosti
 */
final readonly class CmsDataGridColumn
{
    /**
     * Odkazuje na PHP vlastnost domenovych dat, nikoli na SQL od klienta
     */
    public function __construct(
        public string $property,
        public bool $sortable = true,
        public bool $searchable = false,
    ) {}
}
