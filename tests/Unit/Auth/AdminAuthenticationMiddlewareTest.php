<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
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

final class AdminAuthenticationMiddlewareTest extends TestCase
{
    public function testItPassesAnAuthenticatedRequestToTheHandler(): void
    {
        $session = $this->session();
        $this->establishSession($session);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturn(new Response(204));

        $response = $this->middleware($session, new AuthenticatedUser(42, 'admin@example.test'))->process(
            new ServerRequest('GET', '/admin/users'),
            $handler,
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function testItRedirectsAnUnauthenticatedHtmlRouteAndStoresTheSafeDestination(): void
    {
        $session = $this->session();
        $response = $this->middleware($session)->process(
            new ServerRequest('GET', '/admin/users?filter=active'),
            $this->createMock(RequestHandlerInterface::class),
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/login', $response->getHeaderLine('Location'));
        self::assertSame('/admin/users?filter=active', AdminAuthenticationMiddleware::pullIntendedPath($session, $this->routing()));
    }

    public function testItReturnsTheExistingJsonContractForAnUnauthenticatedApiRoute(): void
    {
        $session = $this->session();
        $response = $this->middleware($session)->process(
            new ServerRequest('GET', '/admin/api/datagrid/users'),
            $this->createMock(RequestHandlerInterface::class),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(['error' => ['code' => 'unauthenticated', 'message' => 'Authentication required.']], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame('/admin', AdminAuthenticationMiddleware::pullIntendedPath($session, $this->routing()));
    }

    public function testItReturnsTheExistingJsonContractForAnUnauthenticatedAjaxRoute(): void
    {
        $response = $this->middleware($this->session())->process(
            new ServerRequest('POST', '/admin/languages/ajax/1'),
            $this->createMock(RequestHandlerInterface::class),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['error' => ['code' => 'unauthenticated', 'message' => 'Authentication required.']],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testItExpiresAnIdleSessionBeforeResolvingTheCurrentUser(): void
    {
        $session = $this->session();
        $session->set('admin.auth.user_id', 42);
        $session->set('admin.auth.authenticated_at', time() - 1200);
        $session->set('admin.auth.last_activity_at', time() - 1200);

        $response = $this->middleware($session, new AuthenticatedUser(42, 'admin@example.test'))->process(
            new ServerRequest('POST', '/admin/api/session/heartbeat'),
            $this->createMock(RequestHandlerInterface::class),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($session->get('admin.auth.user_id'));
    }

    private function middleware(SessionInterface $session, ?AuthenticatedUser $user = null): AdminAuthenticationMiddleware
    {
        $principal = $this->createMock(CurrentPrincipalProviderInterface::class);
        $principal->method('currentUser')->willReturn($user);

        return new AdminAuthenticationMiddleware(
            $principal,
            $this->authentication($session),
            $session,
            new Psr17Factory(),
            $this->routing(),
        );
    }

    private function establishSession(SessionInterface $session): void
    {
        $session->set('admin.auth.user_id', 42);
        $session->set('admin.auth.authenticated_at', time());
        $session->set('admin.auth.last_activity_at', time());
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

    private function routing(): AdminRoutingConfiguration
    {
        return new AdminRoutingConfiguration('/admin');
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

            public function regenerate(bool $deleteOldSession = true): void {}
        };
    }
}
