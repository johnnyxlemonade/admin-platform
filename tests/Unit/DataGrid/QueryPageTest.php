<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\Query\QueryPage;
use PHPUnit\Framework\TestCase;

final class QueryPageTest extends TestCase
{
    public function testItExposesTypedPageMetadataAndItems(): void
    {
        $page = new QueryPage([
            ['id' => 1],
            ['id' => 2],
        ], 2, 2, 5);

        self::assertSame([['id' => 1], ['id' => 2]], $page->items());
        self::assertSame(2, $page->page());
        self::assertSame(2, $page->perPage());
        self::assertSame(5, $page->total());
    }

    public function testItRejectsInvalidPaginationMetadata(): void
    {
        $rejected = 0;
        foreach ([[0, 1, 0], [1, 0, 0], [1, 1, -1]] as [$page, $perPage, $total]) {
            try {
                new QueryPage([], $page, $perPage, $total);
            } catch (InvalidArgumentException) {
                $rejected++;
            }
        }

        self::assertSame(3, $rejected);
    }
}
