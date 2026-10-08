<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Localization;

use InvalidArgumentException;
use Lemonade\Admin\Localization\ClientTranslationGroupRegistry;
use PHPUnit\Framework\TestCase;

final class ClientTranslationGroupRegistryTest extends TestCase
{
    public function testItAlwaysExposesTheCanonicalSharedAdminGroup(): void
    {
        $registry = new ClientTranslationGroupRegistry();
        $registry->register('auth');

        self::assertTrue($registry->has('admin'));
        self::assertTrue($registry->has('auth'));
        self::assertFalse($registry->has('messages'));
        self::assertSame(['admin', 'auth'], $registry->groups());
    }

    public function testItRejectsUnsafeGroupNames(): void
    {
        $registry = new ClientTranslationGroupRegistry();

        $this->expectException(InvalidArgumentException::class);

        $registry->register('../auth');
    }
}
