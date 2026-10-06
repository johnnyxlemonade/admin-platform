<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\Http\Middleware\AdminAnonymousMiddleware;
use Lemonade\Admin\Auth\LocalAdminPrincipal;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
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

        $response = (new AdminAnonymousMiddleware($principal, new Psr17Factory(), $this->routing()))->process(
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

        $response = (new AdminAnonymousMiddleware($principal, new Psr17Factory(), $this->routing()))->process(
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
}
