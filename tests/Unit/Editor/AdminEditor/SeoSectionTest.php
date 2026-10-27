<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Editor\AdminEditor\Field\UrlSegmentField;
use Lemonade\Admin\Editor\AdminEditor\Layout\FieldGroupBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\SectionBlock;
use Lemonade\Admin\Editor\AdminEditor\Layout\SeoSection;
use PHPUnit\Framework\TestCase;

/**
 * Overuje spolecnou prezentaci SEO poli a oddelenou URL akci
 */
final class SeoSectionTest extends TestCase
{
    /**
     * Poskytuje v obou jazycich uplne uzivatelske texty SEO sekce
     */
    public function testSeoTranslationsExistInBothAdminLocales(): void
    {
        foreach (['cs', 'en'] as $locale) {
            $catalog = require __DIR__ . '/../../../../src/Resources/lang/' . $locale . '/admin.php';
            foreach (['tab', 'section', 'section_help', 'page_title', 'page_title_help', 'meta_description', 'meta_description_help'] as $key) {
                self::assertNotSame('', $catalog['seo'][$key]);
            }
        }
    }

    /**
     * Sdili popisky a zachova modulovy prefix canonical cesty
     */
    public function testSharedFieldsKeepCanonicalUrlActionAndPrefix(): void
    {
        $tab = SeoSection::tab('/office/', false);
        self::assertSame('admin.seo.tab', $tab->labelKey());

        $section = $tab->blocks()[0];
        self::assertInstanceOf(SectionBlock::class, $section);
        self::assertSame('admin.seo.section', $section->titleKey());
        self::assertSame('admin.seo.section_help', $section->descriptionKey());

        $group = $section->blocks()[0];
        self::assertInstanceOf(FieldGroupBlock::class, $group);
        self::assertSame('admin.seo.page_title', $group->columns()[0]->field()->labelTranslationKey());
        self::assertSame('admin.seo.page_title_help', $group->columns()[0]->field()->helpTranslationKey());
        self::assertSame('admin.seo.meta_description', $group->columns()[1]->field()->labelTranslationKey());
        self::assertSame('admin.seo.meta_description_help', $group->columns()[1]->field()->helpTranslationKey());

        $url = $group->columns()[2]->field();
        self::assertInstanceOf(UrlSegmentField::class, $url);
        self::assertSame('/office/', $url->pathPrefix());
        self::assertSame('change-url', $url->actionKey());
        self::assertFalse($url->autoGenerates());
    }
}
