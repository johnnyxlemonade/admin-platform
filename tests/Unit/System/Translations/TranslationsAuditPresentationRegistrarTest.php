<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\System\Translations;

use Lemonade\Admin\Audit\AuditEventPresentationRegistry;
use Lemonade\Admin\Icon\AdminIcon;
use Lemonade\Admin\System\Translations\Audit\TranslationsAuditPresentationRegistrar;
use PHPUnit\Framework\TestCase;

/**
 * Overuje lokalizovanou auditni prezentaci mutaci vlastnich prekladu
 */
final class TranslationsAuditPresentationRegistrarTest extends TestCase
{
    /**
     * Registruje modulovy nazev a vsechny canonical override udalosti
     */
    public function testItRegistersTranslationOverrideAuditPresentations(): void
    {
        $presentations = new AuditEventPresentationRegistry();
        (new TranslationsAuditPresentationRegistrar($presentations))->register();

        self::assertSame('translations.module.name', $presentations->modulePresentation('system.translations')?->nameKey());
        self::assertSame('translations.audit.events.created', $presentations->presentation('system.translations.override_created')?->translationKey());
        self::assertSame(AdminIcon::PencilSquare, $presentations->presentation('system.translations.override_updated')?->icon());
        self::assertSame('translations.audit.events.removed', $presentations->presentation('system.translations.override_removed')?->translationKey());
    }

    /**
     * Overuje ceske a anglicke nazvy vsech auditnich mutaci override
     */
    public function testTranslationOverrideAuditLabelsExistInBothLocales(): void
    {
        foreach ([
            'cs' => ['Vlastní překlad vytvořen', 'Vlastní překlad upraven', 'Vlastní překlad odstraněn'],
            'en' => ['Custom translation created', 'Custom translation updated', 'Custom translation removed'],
        ] as $locale => $expected) {
            $catalog = require dirname(__DIR__, 4) . '/src/System/Translations/Resources/lang/' . $locale . '/translations.php';

            self::assertSame($expected[0], $catalog['audit']['events']['created']);
            self::assertSame($expected[1], $catalog['audit']['events']['updated']);
            self::assertSame($expected[2], $catalog['audit']['events']['removed']);
        }
    }
}
