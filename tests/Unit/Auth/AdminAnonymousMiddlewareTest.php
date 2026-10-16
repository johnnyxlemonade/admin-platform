<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\Http\Middleware\AdminAnonymousMiddleware;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminAnonymousMiddlewareTest extends TestCase
{
    /**
     * @dataProvider authenticatedAuthEndpoints
     */
    public function testItRedirectsAnAuthenticatedLocalPrincipalBeforeAnonymousAuthHandlersRun(string $method, string $uri): void
    {
        $principal = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principal->method('currentUser')->willReturn(new AuthenticatedUser(1, 'root@example.test'));
        $principal->method('currentPrincipal')->willReturn(new LocalAdminPrincipal(new AuthenticatedUser(1, 'root@example.test')));
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $session = $this->session();
        $session->set('admin.auth.user_id', 1);
        $session->set('admin.auth.authenticated_at', time());
        $session->set('admin.auth.last_activity_at', time());

        $response = (new AdminAnonymousMiddleware($principal, $this->authentication($session), new Psr17Factory(), $this->routing()))->process(
            new ServerRequest($method, $uri),
            $handler,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin', $response->getHeaderLine('Location'));
    }

    public function testItPassesAnonymousPrincipalsToTheAnonymousAuthHandler(): void
    {
        $principal = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principal->method('currentUser')->willReturn(null);
        $principal->method('currentPrincipal')->willReturn(null);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new Response(200));
        $session = $this->session();

        $response = (new AdminAnonymousMiddleware($principal, $this->authentication($session), new Psr17Factory(), $this->routing()))->process(
            new ServerRequest('GET', '/admin/login'),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @return array<string, array{method:string, uri:string}>
     */
    public static function authenticatedAuthEndpoints(): array
    {
        return [
            'login form' => ['method' => 'GET', 'uri' => '/admin/login'],
            'login submit' => ['method' => 'POST', 'uri' => '/admin/login'],
            'forgot-password form' => ['method' => 'GET', 'uri' => '/admin/login/forgot'],
            'forgot-password submit' => ['method' => 'POST', 'uri' => '/admin/login/forgot'],
        ];
    }

    private function routing(): AdminRoutingConfiguration
    {
        return new AdminRoutingConfiguration('/admin');
    }

    private function authentication(SessionInterface $session): AuthenticationService
    {
        $database = new Database(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(DatabaseDriverInterface::class),
        );

        return new AuthenticationService(
            new LocalAuthenticationProvider($database),
            $session,
            $database,
            new CsrfTokenManager($session),
            $this->createMock(AuditLogWriterInterface::class),
        );
    }

    private function session(): SessionInterface
    {
        return new class implements SessionInterface {
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
        };
    }
}
