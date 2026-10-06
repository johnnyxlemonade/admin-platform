<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\DataGrid\Contract\DataGridProviderInterface;
use Lemonade\Admin\DataGrid\DataGridColumnDefinition;
use Lemonade\Admin\DataGrid\DataGridDefinition;
use Lemonade\Admin\DataGrid\DataGridRegistry;
use Lemonade\Admin\DataGrid\DataGridResult;
use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use PHPUnit\Framework\TestCase;

final class DataGridRegistryTest extends TestCase
{
    public function testItRegistersOptionalDataGridCapabilitiesByStableModuleCode(): void
    {
        $registry = new DataGridRegistry();
        $provider = new class implements DataGridProviderInterface {
            public function dataGridDefinition(): DataGridDefinition
            {
                return new DataGridDefinition(
                    'cms.example',
                    [new DataGridColumnDefinition('title', 'cms.example.fields.title', 'title')],
                    false,
                    [],
                    'title',
                    'asc',
                    20,
                    100,
                );
            }

            public function permission(): string
            {
                return 'cms.example.view';
            }

            public function execute(DataGridQuery $query): DataGridResult
            {
                return new DataGridResult([], 1, 20, 0);
            }
        };

        $registry->register('cms.example', $provider);

        self::assertTrue($registry->has('cms.example'));
        self::assertFalse($registry->has('cms.without-grid'));
        self::assertSame($provider, $registry->provider('cms.example'));
    }
}
