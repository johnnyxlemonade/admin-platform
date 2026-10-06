<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Languages;

use Lemonade\Admin\System\Languages\LanguagePresetCatalog;
use PHPUnit\Framework\TestCase;

final class LanguagePresetCatalogTest extends TestCase
{
    /**
     * Overuje uplny a jednoznacny katalog ISO 639-1 presetu
     */
    public function testItProvidesEveryIso6391LanguageWithUsableMetadata(): void
    {
        $presets = LanguagePresetCatalog::all();
        $codes = array_map(static fn($preset): string => $preset->code(), $presets);

        self::assertCount(184, $presets);
        self::assertSame($codes, array_values(array_unique($codes)));

        foreach ($presets as $preset) {
            self::assertMatchesRegularExpression('/\\A[a-z]{2}\\z/D', $preset->code());
            self::assertNotSame('', trim($preset->displayName()));
            self::assertNotSame('', trim($preset->nativeName()));
            self::assertTrue(
                $preset->flagCode() === null || preg_match('/\\A[A-Z]{2}\\z/D', $preset->flagCode()) === 1,
            );
        }
    }

    /**
     * Overuje data pro predvyplneni bez zmeny dynamicke databazoveho katalogu
     */
    public function testItProvidesEditorValuesForEveryPreset(): void
    {
        $values = LanguagePresetCatalog::editorValues();

        self::assertCount(184, $values);
        self::assertSame('čeština', $values['cs']['name']);
        self::assertSame('CZ', $values['cs']['flagCode']);
        self::assertNull($values['zh']['flagCode']);
    }

    /**
     * Overuje anglicky, nativni i ISO vyhledatelny popisek francouzstiny
     */
    public function testFrenchPresetContainsItsCodeAndBothNamesInTheSelectLabel(): void
    {
        self::assertSame('French — français', LanguagePresetCatalog::selectOptions()['fr']);
    }
}
