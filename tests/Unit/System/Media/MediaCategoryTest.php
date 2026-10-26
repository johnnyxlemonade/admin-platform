<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Media;

use Lemonade\Admin\DataGrid\Query\DataGridQuery;
use Lemonade\Admin\Presentation\MediaCategory;
use Lemonade\Admin\Presentation\Models\AdminFileModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Overuje klasifikaci MIME a ulozeny kontrakt Media filtru
 */
final class MediaCategoryTest extends TestCase
{
    /**
     * Overi klasifikaci nezavisle na technickem kindu, usage a upload profilu
     */
    #[DataProvider('fileMetadata')]
    public function testClassifiesStoredFileMetadata(string $mimeType, MediaCategory $expected): void
    {
        self::assertSame($expected, MediaCategory::classify($mimeType));
    }

    /**
     * Overi ze filtr pouziva ulozenou kategorii bez SQL klasifikace MIME
     */
    public function testManagementFilterUsesStoredMediaCategory(): void
    {
        $queries = [];
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(static fn(string $identifier): string => $identifier);
        $driver->method('query')->willReturnCallback(function (string $sql, array|false $bindings) use (&$queries): DatabaseResultInterface {
            $queries[] = ['sql' => $sql, 'bindings' => $bindings === false ? [] : array_values($bindings)];

            return $this->databaseResult();
        });

        (new AdminFileModel($driver))->listForManagement(new DataGridQuery(
            page: 1,
            pageSize: 25,
            sortKey: 'createdAt',
            sortDirection: 'desc',
            search: '',
            filters: ['type' => MediaCategory::Document->value],
        ));

        self::assertCount(2, $queries);
        foreach ($queries as $query) {
            self::assertStringContainsString('f.media_category = ?', $query['sql']);
            self::assertStringNotContainsString('CASE WHEN', $query['sql']);
            self::assertStringNotContainsString('f.kind =', $query['sql']);
            self::assertContains(MediaCategory::Document->value, $query['bindings']);
        }
    }

    /**
     * Vrati canonical metadata pro podporovane i hostem rozsirene media kategorie
     *
     * @return iterable<string, array{string, MediaCategory}>
     */
    public static function fileMetadata(): iterable
    {
        yield 'attachment jpg is image' => ['image/jpeg', MediaCategory::Image];
        yield 'gallery jpg is image' => ['image/jpeg', MediaCategory::Image];
        yield 'thumbnail jpg is image' => ['image/jpeg', MediaCategory::Image];
        yield 'attachment png is image' => ['image/png', MediaCategory::Image];
        yield 'host AVIF is image' => [' Image/AVIF; charset=binary ', MediaCategory::Image];
        yield 'host SVG is image' => ['image/svg+xml', MediaCategory::Image];
        yield 'host GIF is image' => ['image/gif', MediaCategory::Image];
        yield 'pdf is document' => ['application/pdf', MediaCategory::Document];
        yield 'txt is document' => ['text/plain', MediaCategory::Document];
        yield 'doc is document' => ['application/msword', MediaCategory::Document];
        yield 'docx is document' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', MediaCategory::Document];
        yield 'xls is document' => ['application/vnd.ms-excel', MediaCategory::Document];
        yield 'xlsx is document' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', MediaCategory::Document];
        yield 'host ODT is document' => ['application/vnd.oasis.opendocument.text', MediaCategory::Document];
        yield 'host ODS is document' => ['application/vnd.oasis.opendocument.spreadsheet', MediaCategory::Document];
        yield 'video MIME is video' => ['video/mp4', MediaCategory::Video];
        yield 'unknown binary is other' => ['application/octet-stream', MediaCategory::Other];
    }

    /**
     * Vrati prazdny database vysledek pro DB-free query contract test
     */
    private function databaseResult(): DatabaseResultInterface
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('row_array')->willReturn(['numrows' => 0]);
        $result->method('result_array')->willReturn([]);

        return $result;
    }
}
