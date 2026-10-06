<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Localization;

use Lemonade\Admin\Localization\ClientTranslationCatalog;
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
}
