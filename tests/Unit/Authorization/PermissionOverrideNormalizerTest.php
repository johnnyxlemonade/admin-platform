<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Authorization;

use Lemonade\Admin\Authorization\PermissionOverrideNormalizer;
use PHPUnit\Framework\TestCase;

final class PermissionOverrideNormalizerTest extends TestCase
{
    public function testItPersistsOnlyTheDeltaAgainstTheRole(): void
    {
        $normalizer = new PermissionOverrideNormalizer();

        self::assertSame(['system.users.edit' => 'deny'], $normalizer->normalize(
            ['system.users.view', 'system.users.edit'],
            ['system.users.view' => true, 'system.users.edit' => false],
        ));
        self::assertSame(['system.users.create' => 'allow'], $normalizer->normalize(
            ['system.users.view'],
            ['system.users.view' => true, 'system.users.create' => true],
        ));
        self::assertSame([], $normalizer->normalize(['system.users.view'], ['system.users.view' => true]));
        self::assertSame([], $normalizer->normalize(['system.users.view'], ['system.users.create' => false]));
    }

    public function testReturningToInheritedStateRemovesTheOverride(): void
    {
        $normalizer = new PermissionOverrideNormalizer();

        self::assertSame([], $normalizer->normalize(['system.users.edit'], ['system.users.edit' => true]));
        self::assertSame([], $normalizer->normalize([], ['system.users.edit' => false]));
    }
}
