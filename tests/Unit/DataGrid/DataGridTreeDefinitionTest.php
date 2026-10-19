<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\DataGrid\Cell\ScalarCell;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\DataGrid\DataGridTreeRowDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Overuje minimalni typed metadata hierarchickeho DataGridu
 */
final class DataGridTreeDefinitionTest extends TestCase
{
    /**
     * Prenasi tree sloupec a rodice radku bez zmeny bunek
     */
    public function testSerializesTreeMetadata(): void
    {
        $definition = new DataGridDefinition(
            id: 'pages',
            columns: [new DataGridColumnDefinition('title', 'pages.title', 'title')],
            searchEnabled: true,
            filters: [],
            defaultSortKey: 'title',
            defaultSortDirection: 'asc',
            defaultPageSize: 100,
            maximumPageSize: 100,
            treeColumnKey: 'title',
            paginationEnabled: false,
        );
        $row = new DataGridRowDefinition(
            id: 12,
            cells: ['title' => new ScalarCell('Child')],
            tree: new DataGridTreeRowDefinition(7),
        );

        self::assertSame('title', $definition->treeColumnKey());
        self::assertFalse($definition->paginationEnabled());
        $serialized = $row->toArray();
        self::assertSame(['parentId' => 7], $serialized['tree'] ?? null);
    }
}
