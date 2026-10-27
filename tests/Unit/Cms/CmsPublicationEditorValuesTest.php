<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Cms;

use Lemonade\Admin\Cms\Content\CmsPublicationEditorValues;
use PHPUnit\Framework\TestCase;

final class CmsPublicationEditorValuesTest extends TestCase
{
    public function testSelectsSavedLocaleAndUsesDatetimeLocalFormat(): void
    {
        $translations = [
            ['locale' => 'en', 'publication_state' => 1, 'published_at' => null],
            ['locale' => 'cs', 'publication_state' => 2, 'published_at' => '2028-02-01 12:30:00'],
        ];

        self::assertSame(
            ['publication_state' => '2', 'published_at' => '2028-02-01T12:30'],
            CmsPublicationEditorValues::forLocale($translations, 'cs'),
        );
    }

    public function testUnpublishedDateIsEmpty(): void
    {
        self::assertSame(
            ['publication_state' => '1', 'published_at' => ''],
            CmsPublicationEditorValues::forLocale([
                ['locale' => 'cs', 'publication_state' => 1, 'published_at' => null],
            ], 'cs'),
        );
    }

    public function testNewTranslationAppearsAsDraft(): void
    {
        self::assertSame(
            ['publication_state' => '1', 'published_at' => ''],
            CmsPublicationEditorValues::forLocale([], 'cs'),
        );
    }
}
