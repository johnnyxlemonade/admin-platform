<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Presentation;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Presentation\AdminAvatarInitials;
use PHPUnit\Framework\TestCase;

final class AdminAvatarInitialsTest extends TestCase
{
    public function testItUsesLocalFirstAndLastName(): void
    {
        self::assertSame('JM', AdminAvatarInitials::fromValues('Jan', 'Malý', null, null));
        self::assertSame('TN', AdminAvatarInitials::fromValues('Tomáš', 'Novák', null, null));
    }

    public function testItUsesTheAvailableLocalUser(): void
    {
        $localUser = new AuthenticatedUser(
            id: 7,
            email: 'jan.maly@example.test',
            firstName: 'Jan',
            lastName: 'Malý',
        );
        self::assertSame('JM', AdminAvatarInitials::resolve($localUser));
    }

    public function testItUsesTheFirstAndLastWordsOfDisplayName(): void
    {
        self::assertSame('RA', AdminAvatarInitials::fromValues(null, null, 'Root Administrator', null));
        self::assertSame('JN', AdminAvatarInitials::fromValues(null, null, 'Jan Karel Novák', null));
    }

    public function testItUsesOneInitialForASingleDisplayNameWord(): void
    {
        self::assertSame('D', AdminAvatarInitials::fromValues(null, null, '  dev  ', null));
    }

    public function testItFallsBackToTheEmailLocalPart(): void
    {
        self::assertSame('D', AdminAvatarInitials::fromValues(null, null, null, 'dev-root@ddev.site'));
    }

    public function testItReturnsSafeFallbackForMissingValues(): void
    {
        self::assertSame('?', AdminAvatarInitials::fromValues(null, null, '   ', ''));
    }
}
