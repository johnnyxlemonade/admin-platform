<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Dashboard;

use PHPUnit\Framework\TestCase;

final class DashboardWidgetLocalizationContractTest extends TestCase
{
    public function testProductionWidgetTranslationsExistInCzechAndEnglishCatalogs(): void
    {
        foreach (['cs', 'en'] as $locale) {
            $users = $this->catalog('src/System/Users/Resources/lang/' . $locale . '/users.php');
            $audit = $this->catalog('src/System/Audit/Resources/lang/' . $locale . '/audit.php');
            $modules = $this->catalog('src/System/Modules/Resources/lang/' . $locale . '/modules.php');

            self::assertIsString($users['widgets']['active']['title'] ?? null);
            self::assertIsString($users['widgets']['active']['description'] ?? null);
            self::assertIsString($users['widgets']['active']['metric'] ?? null);
            self::assertIsString($audit['widgets']['recent']['title'] ?? null);
            self::assertIsString($audit['widgets']['recent']['description'] ?? null);
            self::assertIsString($audit['widgets']['recent']['empty'] ?? null);
            self::assertIsString($audit['widgets']['recent']['view_all'] ?? null);
            self::assertIsString($modules['widgets']['active']['title'] ?? null);
            self::assertIsString($modules['widgets']['active']['description'] ?? null);
            self::assertIsString($modules['widgets']['active']['empty'] ?? null);
        }
    }

    /** @return array<string,mixed> */
    private function catalog(string $path): array
    {
        $catalog = require dirname(__DIR__, 3) . '/' . $path;
        self::assertIsArray($catalog);

        return $catalog;
    }
}
