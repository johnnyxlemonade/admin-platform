<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Middleware;

use Lemonade\Admin\Auth\AdminSessionState;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Povoluje omezeny pristup identifikovane aktivni nebo uzamcene relaci
 */
final readonly class AdminSessionIdentityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationService $authentication,
        private CurrentPrincipalProviderInterface $currentPrincipal,
        private Psr17Factory $responses,
        private AdminRoutingConfiguration $routing,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $state = $this->authentication->sessionState();
        if (($state === AdminSessionState::ACTIVE || $state === AdminSessionState::LOCKED)
            && $this->currentPrincipal->currentUser() !== null) {
            return $handler->handle($request);
        }

        if (AdminAuthenticationMiddleware::isApiRequest($request, $this->routing)) {
            return AdminAuthenticationMiddleware::unauthenticatedApiResponse($this->responses);
        }

        return $this->responses->createResponse(302)->withHeader('Location', $this->routing->path('/login'));
    }
}
