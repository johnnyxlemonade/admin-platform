<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Editor;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Editor\Lock\EditorLockIdentity;
use PHPUnit\Framework\TestCase;

final class EditorLockIdentityTest extends TestCase
{
    public function testLocalIdentityUsesCanonicalUserKey(): void
    {
        $identity = EditorLockIdentity::local(new AuthenticatedUser(42, 'local@example.test'));

        self::assertSame('user:42', $identity->key());
        self::assertSame(42, $identity->localUserId());
    }
}
