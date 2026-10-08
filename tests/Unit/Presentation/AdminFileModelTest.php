<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\TestCase;

final class AdminFileModelTest extends TestCase
{
    public function testListsMultipleOwnerUsagesInOneOrderedQuery(): void
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn([
            [
                'id' => 14,
                'usage' => 'attachment',
                'kind' => 'document',
                'original_filename' => 'program.pdf',
                'display_name' => 'Program slavnosti',
                'caption' => null,
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => 1234,
                'width' => null,
                'height' => null,
                'sort_order' => 0,
            ],
            [
                'id' => 11,
                'usage' => 'gallery',
                'kind' => 'image',
                'original_filename' => 'park.jpg',
                'display_name' => null,
                'caption' => 'Park',
                'extension' => 'jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 2345,
                'width' => 1600,
                'height' => 900,
                'sort_order' => 0,
            ],
        ]);
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->expects(self::once())
            ->method('query')
            ->willReturnCallback(function (string $sql, array|false $bindings) use ($result): DatabaseResultInterface {
                self::assertStringContainsString('usage IN (?, ?)', $sql);
                self::assertStringContainsString('ORDER BY usage ASC, sort_order ASC, id ASC', $sql);
                self::assertSame(['cms.news', 3, 'gallery', 'attachment'], $bindings);

                return $result;
            });

        $files = (new AdminFileModel($driver))->listForEntityUsages(
            'cms.news',
            3,
            ['gallery', 'attachment'],
        );

        self::assertSame(14, $files[0]['id']);
        self::assertSame(11, $files[1]['id']);
    }

    public function testDoesNotQueryWhenNoUsageIsRequested(): void
    {
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->expects(self::never())->method('query');

        self::assertSame([], (new AdminFileModel($driver))->listForEntityUsages('cms.news', 3, []));
    }
}
