<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\DataGrid;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\Cell\CodeCell;
use Lemonade\Admin\DataGrid\Cell\DataGridCell;
use Lemonade\Admin\DataGrid\Cell\DataGridCellSerializer;
use Lemonade\Admin\DataGrid\Cell\DateTimeCell;
use Lemonade\Admin\DataGrid\Cell\FlagCell;
use Lemonade\Admin\DataGrid\Cell\FlagLinkCell;
use Lemonade\Admin\DataGrid\Cell\LinkCell;
use Lemonade\Admin\DataGrid\Cell\ScalarCell;
use Lemonade\Admin\DataGrid\Cell\StackedCell;
use Lemonade\Admin\DataGrid\Cell\StatusCell;
use Lemonade\Admin\DataGrid\Cell\StatusVariant;
use Lemonade\Admin\DataGrid\Cell\ThumbnailCell;
use Lemonade\Admin\DataGrid\Cell\TranslationCell;
use Lemonade\Admin\DataGrid\DataGridRowDefinition;
use Lemonade\Admin\Presentation\AdminThumbnail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DataGridCellTest extends TestCase
{
    /** @return iterable<string, array{DataGridCell, string|int|array<int, array<string, mixed>>}> */
    public static function presentations(): iterable
    {
        yield 'scalar text' => [new ScalarCell('Plain text'), 'Plain text'];
        yield 'scalar number' => [new ScalarCell(12), 12];
        yield 'link' => [new LinkCell('Edit', '/admin/items/edit/1'), [['type' => 'link', 'value' => 'Edit', 'url' => '/admin/items/edit/1']]];
        yield 'modal link with flag' => [new FlagLinkCell('Czech', '#', 'CZ', '/admin/languages/edit/1/modal'), [['type' => 'link', 'value' => 'Czech', 'url' => '#', 'flag' => '🇨🇿', 'modalUrl' => '/admin/languages/edit/1/modal']]];
        yield 'modal link with explicit size' => [new LinkCell('Edit', '#', '/admin/languages/edit/1/modal', 'large'), [['type' => 'link', 'value' => 'Edit', 'url' => '#', 'modalUrl' => '/admin/languages/edit/1/modal', 'modalSize' => 'large']]];
        yield 'flag' => [new FlagCell('Czech', 'CZ'), [['type' => 'flag', 'value' => 'Czech', 'flag' => '🇨🇿']]];
        yield 'code' => [new CodeCell('system.news'), [['type' => 'code', 'value' => 'system.news']]];
        yield 'plain status' => [new StatusCell('Enabled', StatusVariant::Success), [['type' => 'status', 'value' => 'Enabled', 'class' => 'status-success']]];
        yield 'translated status' => [new StatusCell('Inactive', StatusVariant::Muted, 'users.status.inactive'), [['type' => 'status', 'value' => 'Inactive', 'class' => 'status-muted', 'translationKey' => 'users.status.inactive']]];
        yield 'translation' => [new TranslationCell('users.list.never_logged_in', 'Never'), [['type' => 'translation', 'translationKey' => 'users.list.never_logged_in', 'value' => 'Never']]];
        yield 'date time' => [new DateTimeCell('2026-09-22 10:30:00'), [['type' => 'datetime', 'value' => '2026-09-22 10:30:00']]];
        yield 'stacked text' => [new StackedCell('3.5 MB', '1920 × 1080'), [['type' => 'stacked', 'value' => '3.5 MB', 'secondary' => '1920 × 1080']]];
        yield 'linked thumbnail' => [new ThumbnailCell('user@example.test', self::thumbnail(), 'Administrator', '/admin/users/edit/1'), [['type' => 'thumbnail', 'thumbnail' => self::thumbnail()->toArray(), 'value' => 'user@example.test', 'url' => '/admin/users/edit/1', 'secondary' => 'Administrator']]];
        yield 'download thumbnail' => [new ThumbnailCell('report.pdf', self::thumbnail(), 'Document', '/admin/system/media/4/download', true), [['type' => 'thumbnail', 'thumbnail' => self::thumbnail()->toArray(), 'value' => 'report.pdf', 'url' => '/admin/system/media/4/download', 'secondary' => 'Document', 'download' => true]]];
        yield 'thumbnail without link' => [new ThumbnailCell('user@example.test', self::thumbnail(), 'Administrator'), [['type' => 'thumbnail', 'thumbnail' => self::thumbnail()->toArray(), 'value' => 'user@example.test', 'url' => null, 'secondary' => 'Administrator']]];
    }

    /** @param string|int|array<int, array<string, mixed>> $expected */
    #[DataProvider('presentations')]
    public function testCellsSerializeToTheExistingClientContract(DataGridCell $cell, string|int|array $expected): void
    {
        self::assertSame($expected, DataGridCellSerializer::serialize($cell));
    }

    public function testLinksRequireAUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LinkCell('Invalid', '');
    }

    public function testTranslationCellsRequireATranslationKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TranslationCell('', 'Fallback');
    }

    public function testStatusCannotUseAnEmptyOptionalTranslationKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StatusCell('Invalid', StatusVariant::Muted, '');
    }

    public function testRowsRejectLegacyCellArrays(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ReflectionClass(DataGridRowDefinition::class))->newInstance(1, [
            'legacy' => [['type' => 'link', 'value' => 'Legacy', 'url' => '/legacy']],
        ]);
    }

    public function testRowOwnsTheCanonicalCellSerializationBoundary(): void
    {
        $row = new DataGridRowDefinition(7, [
            'title' => new ScalarCell('News'),
            'state' => new StatusCell('Draft', StatusVariant::Muted, 'news.state.draft'),
        ]);

        self::assertSame([
            'id' => 7,
            'cells' => [
                'title' => 'News',
                'state' => [['type' => 'status', 'value' => 'Draft', 'class' => 'status-muted', 'translationKey' => 'news.state.draft']],
            ],
            'actions' => [],
        ], $row->toArray());
    }

    private static function thumbnail(): AdminThumbnail
    {
        return new AdminThumbnail(
            url: '/api/image/system.users/datagrid/1',
            alt: '',
            fallback: 'U',
            size: 'compact',
            shape: 'circle',
        );
    }
}
