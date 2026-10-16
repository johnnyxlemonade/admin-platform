<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use Lemonade\Admin\DataGrid\DataGridRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Overuje root-safe katalog datagrid provideru
 */
final class DataGridRegistryTest extends TestCase
{
    /**
     * Overuje registraci service ID bez runtime instance
     */
    public function testItRegistersProviderServiceIdByStableModuleCode(): void
    {
        $registry = new DataGridRegistry();
        $registry->register('cms.example', 'cms.example.grid');

        self::assertTrue($registry->has('cms.example'));
        self::assertFalse($registry->has('cms.without-grid'));
        self::assertSame('cms.example.grid', $registry->providerServiceId('cms.example'));
    }
}
