<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Identity;

use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use PHPUnit\Framework\TestCase;

final class LocalActorGuardTest extends TestCase
{
    public function testItReturnsTheCurrentLocalUser(): void
    {
        $user = new AuthenticatedUser(42, 'admin@example.test');

        self::assertSame($user, $this->guard(new LocalAdminPrincipal($user), $user)->requireLocalUser());
    }

    public function testItRejectsAMissingLocalUserBeforeALocalOnlyMutation(): void
    {
        $guard = $this->guard(null, null);

        $this->expectException(LocalActorRequiredException::class);
        $this->expectExceptionMessage('A local user is required.');
        $guard->requireLocalUser();
    }

    private function guard(?AdminPrincipalInterface $principal, ?AuthenticatedUser $user): LocalActorGuard
    {
        $provider = new class ($principal, $user) implements CurrentPrincipalProviderInterface {
            public function __construct(private readonly ?AdminPrincipalInterface $principal, private readonly ?AuthenticatedUser $user) {}

            public function currentPrincipal(): ?AdminPrincipalInterface
            {
                return $this->principal;
            }

            public function currentUser(): ?AuthenticatedUser
            {
                return $this->user;
            }
        };

        return new LocalActorGuard($provider);
    }
}
