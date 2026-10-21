<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor\AdminEditor;

use Lemonade\Admin\Editor\AdminEditor\Header\AdminEditorHeaderContextDefinition;
use Lemonade\Admin\Editor\AdminEditor\Header\AdminEditorHeaderContextItem;
use PHPUnit\Framework\TestCase;

/**
 * Overuje metadata jazykovych mutaci sdileneho kontextu editoru
 */
final class AdminEditorHeaderContextDefinitionTest extends TestCase
{
    /**
     * Odvozuje souhrn pouze ze spolehlive zname existence mutaci
     */
    public function testItCountsExistingTranslations(): void
    {
        $context = new AdminEditorHeaderContextDefinition('Language versions', [
            new AdminEditorHeaderContextItem('Czech', '/cs', code: 'cs', translationExists: true),
            new AdminEditorHeaderContextItem('English', '/en', code: 'en', translationExists: false),
            new AdminEditorHeaderContextItem('German', '/de', code: 'de', translationExists: true),
        ]);

        self::assertSame(['existing' => 2, 'total' => 3], $context->translationCounts());
    }

    /**
     * Nezobrazuje zavadejici souhrn pri chybejicich metadatech
     */
    public function testItOmitsCountsWithoutCompleteTranslationMetadata(): void
    {
        $context = new AdminEditorHeaderContextDefinition('Other context', [
            new AdminEditorHeaderContextItem('First', '/first'),
            new AdminEditorHeaderContextItem('Second', '/second', translationExists: true),
        ]);

        self::assertNull($context->translationCounts());
    }
}
