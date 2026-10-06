<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Flag;

use InvalidArgumentException;
use Lemonade\Admin\Flag\AdminCountryFlag;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AdminCountryFlagTest extends TestCase
{
    public function testItConvertsNormalizedCountryCodesToUnicodeRegionalIndicators(): void
    {
        $czech = AdminCountryFlag::from('CZ');
        $british = AdminCountryFlag::from('GB');

        self::assertSame('CZ', $czech->countryCode());
        self::assertSame('🇨🇿', $czech->unicode());
        self::assertSame('🇬🇧', $british->unicode());
    }

    public function testItRejectsNonNormalizedCountryCodes(): void
    {
        foreach (['cz', ' C Z', 'CZE', 'C1', 'ČZ', ''] as $countryCode) {
            self::assertNull(AdminCountryFlag::tryFrom($countryCode));
        }

        $this->expectException(InvalidArgumentException::class);
        AdminCountryFlag::from('cz');
    }

    public function testItIsImmutable(): void
    {
        self::assertTrue((new ReflectionProperty(AdminCountryFlag::class, 'countryCode'))->isReadOnly());
    }
}
