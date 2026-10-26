<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\File;

use Lemonade\Admin\File\FileMetadata;
use Lemonade\Admin\File\FileReadModel;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\DatabaseResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Overuje sdilene typed cteni canonical katalogu souboru
 */
final class FileReadModelTest extends TestCase
{
    /**
     * Nacte vice usage jednoho vlastnika v canonical poradi
     */
    public function testListsEntityUsagesInCanonicalOrder(): void
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn([
            $this->file([
                'id' => 14,
                'usage' => 'attachment',
                'kind' => 'file',
                'media_category' => 'document',
                'original_filename' => 'program.pdf',
                'display_name' => 'Program slavnosti',
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'width' => null,
                'height' => null,
            ]),
            $this->file([
                'id' => 11,
                'usage' => 'gallery',
                'caption' => 'Park',
            ]),
        ]);
        $driver = $this->driver();
        $driver->expects(self::once())
            ->method('query')
            ->willReturnCallback(function (string $sql, array|false $bindings) use ($result): DatabaseResultInterface {
                self::assertStringContainsString('usage IN (?, ?)', $sql);
                self::assertStringContainsString('ORDER BY usage ASC, sort_order ASC, id ASC', $sql);
                self::assertSame(['cms.news', 3, 'gallery', 'attachment'], $bindings);

                return $result;
            });

        $files = (new FileReadModel($driver))->listForEntityUsages(
            'cms.news',
            3,
            ['gallery', 'attachment'],
        );

        self::assertContainsOnlyInstancesOf(FileMetadata::class, $files);
        self::assertSame([14, 11], array_map(
            static fn(FileMetadata $file): int => $file->id(),
            $files,
        ));
        self::assertSame('Program slavnosti', $files[0]->displayName());
        self::assertSame('Park', $files[1]->caption());
    }

    /**
     * Nacte nejnovejsi image kazde entity jednim davkovym dotazem
     */
    public function testFindsLatestImagesForEntitiesWithoutNPlusOneQueries(): void
    {
        $result = $this->createMock(DatabaseResultInterface::class);
        $result->method('result_array')->willReturn([
            $this->file(['id' => 9, 'entity_id' => 3]),
            $this->file(['id' => 7, 'entity_id' => 3]),
            $this->file(['id' => 11, 'entity_id' => 8]),
        ]);
        $driver = $this->driver();
        $driver->expects(self::once())
            ->method('query')
            ->willReturnCallback(function (string $sql, array|false $bindings) use ($result): DatabaseResultInterface {
                self::assertStringContainsString('entity_id IN (?, ?)', $sql);
                self::assertStringContainsString('kind = ?', $sql);
                self::assertStringContainsString('ORDER BY entity_id ASC, id DESC', $sql);
                self::assertSame(['cms.news', 3, 8, 'thumbnail', 'image'], $bindings);

                return $result;
            });

        $files = (new FileReadModel($driver))->findForEntities(
            'cms.news',
            [3, 8],
            'thumbnail',
            'image',
        );

        self::assertSame([3, 8], array_keys($files));
        self::assertSame(9, $files[3]->id());
        self::assertSame(11, $files[8]->id());
    }

    /**
     * Prazdny batch ani prazdny seznam usage neotevre databazovy dotaz
     */
    public function testSkipsQueriesForEmptySelections(): void
    {
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->expects(self::never())->method('query');
        $files = new FileReadModel($driver);

        self::assertSame([], $files->findForEntities('cms.page', [], 'thumbnail', 'image'));
        self::assertSame([], $files->listForEntityUsages('cms.page', 7, []));
    }

    /**
     * Vytvori database driver s pripravenym vysledkem
     */
    private function driver(): DatabaseDriverInterface&MockObject
    {
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('protect_identifiers')->willReturnCallback(
            static fn(string $identifier): string => $identifier,
        );

        return $driver;
    }

    /**
     * Vrati uplny system_file radek s volitelnymi zmenami
     *
     * @param array<string,int|string|null> $overrides
     * @return array<string,int|string|null>
     */
    private function file(array $overrides): array
    {
        return array_replace([
            'id' => 1,
            'module_code' => 'cms.news',
            'entity_id' => 3,
            'usage' => 'thumbnail',
            'kind' => 'image',
            'media_category' => 'image',
            'asset_id' => 'asset-id',
            'source_version' => 'source-version',
            'storage_path' => null,
            'original_filename' => 'obrazek.jpg',
            'display_name' => null,
            'caption' => null,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 2048,
            'width' => 1600,
            'height' => 900,
            'sort_order' => 0,
            'created_at' => '2026-10-26 10:00:00',
            'updated_at' => '2026-10-26 10:00:00',
        ], $overrides);
    }
}
