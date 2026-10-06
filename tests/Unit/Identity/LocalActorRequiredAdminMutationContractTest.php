<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Identity;

use Lemonade\Admin\Action\Exception\ModuleActionException;
use Lemonade\Admin\Auth\AdminPrincipalInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Identity\LocalActorGuard;
use Lemonade\Admin\Identity\LocalActorRequiredException;
use Lemonade\Admin\System\Languages\Services\LanguageService;
use Lemonade\Admin\System\Roles\Services\RoleService;
use Lemonade\Admin\System\Users\Actions\UsersDeleteAction;
use Lemonade\Admin\System\Users\Services\UserService;
use PHPUnit\Framework\TestCase;

final class LocalActorRequiredAdminMutationContractTest extends TestCase
{
    public function testLanguageMutationsRejectAMissingLocalUserBeforeLoadingTheLanguage(): void
    {
        $service = $this->service(LanguageService::class);
        $this->setPrivateProperty($service, 'actors', $this->anonymousGuard());

        $this->expectException(LocalActorRequiredException::class);
        $service->setEnabled(42, true);
    }

    public function testRoleMutationsRejectAMissingLocalUserBeforeLoadingTheRole(): void
    {
        $service = $this->service(RoleService::class);
        $this->setPrivateProperty($service, 'actors', $this->anonymousGuard());

        $this->expectException(LocalActorRequiredException::class);
        $service->create('editors', 'Editors', null, []);
    }

    public function testUserMutationsRejectAMissingLocalUserWithAStableActionError(): void
    {
        $action = new UsersDeleteAction(
            $this->service(UserService::class),
            $this->anonymousGuard(),
        );

        try {
            $action->execute(42, []);
            self::fail('Expected the local actor capability error.');
        } catch (ModuleActionException $exception) {
            self::assertSame(403, $exception->status());
            self::assertSame('local_actor_required', $exception->errorCode());
        }
    }

    public function testLocalPrincipalPassesTheSharedGuardBeforeTheUserMutationService(): void
    {
        $action = new UsersDeleteAction(
            $this->service(UserService::class),
            $this->localGuard(),
        );

        $this->expectException(\Error::class);
        $action->execute(42, []);
    }

    /** @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function service(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    private function anonymousGuard(): LocalActorGuard
    {
        return $this->guard(null, null);
    }

    private function localGuard(): LocalActorGuard
    {
        $user = new AuthenticatedUser(7, 'local@example.test');

        return $this->guard(new LocalAdminPrincipal($user), $user);
    }

    private function guard(?AdminPrincipalInterface $principal, ?AuthenticatedUser $user): LocalActorGuard
    {
        $provider = new class ($principal, $user) implements CurrentPrincipalProviderInterface {
            public function __construct(
                private readonly ?AdminPrincipalInterface $principal,
                private readonly ?AuthenticatedUser $user,
            ) {}

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

    private function setPrivateProperty(object $object, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setValue($object, $value);
    }
}
