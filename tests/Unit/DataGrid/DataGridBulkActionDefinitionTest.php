<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\Action\DataGridBulkActionDefinition;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\Icon\AdminIcon;
use PHPUnit\Framework\TestCase;

final class DataGridBulkActionDefinitionTest extends TestCase
{
    public function testGridOptInDependsOnTypedBulkActions(): void
    {
        $columns = [new DataGridColumnDefinition('createdAt', 'audit.fields.created_at', 'createdAt')];
        $withoutSelection = new DataGridDefinition('audit', $columns, false, [], 'createdAt', 'asc', 20, 100);
        $withSelection = new DataGridDefinition(
            id: 'notifications',
            columns: $columns,
            searchEnabled: false,
            filters: [],
            defaultSortKey: 'createdAt',
            defaultSortDirection: 'asc',
            defaultPageSize: 20,
            maximumPageSize: 100,
            bulkActions: [new DataGridBulkActionDefinition(
                label: 'Export',
                icon: AdminIcon::BoxArrowUpRight,
                endpoint: '/admin/notification-management/ajax',
                action: 'bulk-export',
                allowedViews: ['all'],
            )],
        );

        self::assertFalse($withoutSelection->selectionEnabled());
        self::assertTrue($withSelection->selectionEnabled());
        self::assertSame(['all'], $withSelection->bulkActions()[0]->allowedViews());
        self::assertFalse($withSelection->bulkActions()[0]->download());
    }

    public function testBulkActionRejectsAnEmptyViewContract(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataGridBulkActionDefinition(
            label: 'Export',
            icon: AdminIcon::BoxArrowUpRight,
            endpoint: '/admin/notification-management/ajax',
            action: 'bulk-export',
            allowedViews: [],
        );
    }
}
