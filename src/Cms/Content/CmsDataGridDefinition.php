<?php

declare(strict_types=1);

namespace Lemonade\Admin\Cms\Content;

/**
 * Doplnuje standardni CMS dotaz o domenove sloupce a filtry
 */
final readonly class CmsDataGridDefinition
{
    /**
     * Nastavuje domenovou projekci a vychozi registrovany klic razeni
     *
     * @param list<CmsDataGridColumn> $columns
     * @param list<CmsDataGridFilter> $filters
     */
    public function __construct(
        public array $columns = [],
        public array $filters = [],
        public string $defaultSort = 'publishedAt',
    ) {}
}
