<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AdminAuthServiceProvider;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Authorization\AuthorizationResolverInterface;
use Lemonade\Admin\Authorization\AuthorizationService;
use Lemonade\Admin\Authorization\CoreAuthorizationServiceProvider;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Overuje canonical requestovou identitu auth a authorization sluzeb
 */
final class RequestIdentityScopeTest extends TestCase
{
    /**
     * Overuje sdilenou identitu v requestu a izolaci dvou requestu
     */
    public function testIdentityAndAuthorizationAreCanonicalOnlyInsideOneRequest(): void
    {
        $container = new Container();
        (new AdminAuthServiceProvider())->register($container);
        (new CoreAuthorizationServiceProvider())->register($container);
        $container->instance(LocalAuthenticationProvider::class, $this->withoutConstructor(LocalAuthenticationProvider::class));
        $container->instance(Database::class, $this->withoutConstructor(Database::class));
        $container->instance(CsrfTokenManager::class, $this->withoutConstructor(CsrfTokenManager::class));
        $container->instance(AuditLogWriterInterface::class, $this->createMock(AuditLogWriterInterface::class));
        $container->scoped(SessionInterface::class, RequestIdentitySessionStub::class);
        $container->freeze();

        $firstScope = $container->beginScope(ScopeKind::Request);
        $secondScope = $container->beginScope(ScopeKind::Request);

        self::assertSame($firstScope->get(AuthenticationService::class), $firstScope->get(AuthenticationService::class));
        self::assertSame($firstScope->get(CurrentUserProvider::class), $firstScope->get(CurrentPrincipalProviderInterface::class));
        self::assertSame($firstScope->get(AuthorizationService::class), $firstScope->get(AuthorizationResolverInterface::class));
        self::assertNotSame($firstScope->get(CurrentUserProvider::class), $secondScope->get(CurrentUserProvider::class));
        self::assertNotSame($firstScope->get(AuthorizationService::class), $secondScope->get(AuthorizationService::class));

        $firstScope->close();
        $secondScope->close();
    }

    /**
     * Vytvori konstruktorove nezavisly objekt pro identity test
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function withoutConstructor(string $class): object
    {
        return (new ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}

/**
 * Poskytuje requestove session uloziste pro identity test
 */
final class RequestIdentitySessionStub implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}

    public function started(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function destroy(): void
    {
        $this->clear();
    }

    public function regenerate(bool $deleteOldSession = true): void {}
}
