<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Localization;

use Lemonade\Admin\Localization\ClientTranslationCatalog;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\FileTranslator;
use Lemonade\Framework\Localization\TranslationResourceRegistry;
use Lemonade\Framework\Localization\TranslatorInterface;
use PHPUnit\Framework\TestCase;

final class ClientTranslationCatalogTest extends TestCase
{
    public function testItExportsAFlattenedTranslatorGroupAsANestedCatalog(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('group')
            ->with('auth', 'en')
            ->willReturn([
                'login.title' => 'Sign in',
                'login.errors.invalid' => 'The sign-in details are invalid.',
                'close' => 'Close',
            ]);

        $catalog = (new ClientTranslationCatalog($translator))->export('auth', 'en');

        self::assertSame([
            'login' => [
                'title' => 'Sign in',
                'errors' => ['invalid' => 'The sign-in details are invalid.'],
            ],
            'close' => 'Close',
        ], $catalog);
    }

    public function testItExportsSharedAdminConfirmAndDataGridTranslationsForSupportedLocales(): void
    {
        $resources = new TranslationResourceRegistry();
        $resources->register(dirname(__DIR__, 3) . '/src/Resources/lang', 'admin');
        $translator = new FileTranslator(
            new ApplicationContext(
                Environment::Testing,
                new Path(sys_get_temp_dir()),
                DebugMode::disabled(),
            ),
            new LocalizationConfig(
                defaultLocale: 'cs',
                fallbackLocale: 'en',
                supportedLocales: ['cs', 'en'],
                url: new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false),
            ),
            $resources,
        );
        $catalog = new ClientTranslationCatalog($translator);

        self::assertSame(
            [
                'confirm' => 'Potvrdit',
                'cancel' => 'Zrušit',
                'continue' => 'Pokračovat',
                'summary' => 'Zobrazeno {from}–{to} z {total} položek',
            ],
            $this->sharedAdminTranslations($catalog->export('admin', 'cs')),
        );
        self::assertSame(
            [
                'confirm' => 'Confirm',
                'cancel' => 'Cancel',
                'continue' => 'Continue',
                'summary' => 'Showing {from}–{to} of {total} items',
            ],
            $this->sharedAdminTranslations($catalog->export('admin', 'en')),
        );
    }

    /**
     * Overuje dostupnost sdilene auth skupiny pro login i MFA ve vsech podporovanych locale
     */
    public function testItExportsSharedAuthTranslationsForMfaInCzechAndEnglish(): void
    {
        $resources = new TranslationResourceRegistry();
        $resources->register(dirname(__DIR__, 3) . '/src/Auth/Resources/lang', 'admin.auth');
        $translator = new FileTranslator(
            new ApplicationContext(
                Environment::Testing,
                new Path(sys_get_temp_dir()),
                DebugMode::disabled(),
            ),
            new LocalizationConfig(
                defaultLocale: 'cs',
                fallbackLocale: 'en',
                supportedLocales: ['cs', 'en'],
                url: new LocalizationUrlConfig(false, 'localized.', '/{locale}', 'locale', false),
            ),
            $resources,
        );
        $catalog = new ClientTranslationCatalog($translator);

        self::assertSame('Ověřovací kód', $catalog->export('auth', 'cs')['mfa']['code']);
        self::assertSame('Authentication code', $catalog->export('auth', 'en')['mfa']['code']);
    }

    /**
     * Vybere sdilene texty, ktere potrebuji confirm dialog a DataGrid
     *
     * @param array<string, mixed> $catalog
     * @return array{confirm: string, cancel: string, continue: string, summary: string}
     */
    private function sharedAdminTranslations(array $catalog): array
    {
        return [
            'confirm' => $catalog['common']['confirm'],
            'cancel' => $catalog['common']['cancel'],
            'continue' => $catalog['common']['continue'],
            'summary' => $catalog['datagrid']['summary'],
        ];
    }
}
