<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Localization;

use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Overuje jazyky rozhrani administrace nezavisle na jazycich verejneho obsahu
 */
final class AdminUiLocaleTest extends TestCase
{
    /**
     * Overuje, ze anglictinu lze aktivovat i bez konfigurace jazyku aplikace
     */
    public function testEnglishUiLocaleCanBeActivated(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::once())->method('start');
        $session->expects(self::once())->method('set')->with('admin.ui.locale', 'en');
        $session->method('get')->with('admin.ui.locale')->willReturn('en');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('setLocale')->with('en')->willReturnSelf();

        $locale = new AdminUiLocale($session, $translator);

        self::assertSame(['cs', 'en'], $locale->supportedLocales());
        self::assertTrue($locale->supports('en'));
        self::assertSame('en', $locale->activate('en'));
    }

    /**
     * Vraci cestinu pro nepodporovany jazyk rozhrani
     */
    public function testUnsupportedUiLocaleFallsBackToCzech(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $locale = new AdminUiLocale($session, $translator);

        self::assertFalse($locale->supports('de'));
        self::assertSame('cs', $locale->resolve('de'));
    }
}
