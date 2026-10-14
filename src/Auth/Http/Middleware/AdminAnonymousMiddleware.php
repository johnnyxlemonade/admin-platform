<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Middleware;

use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Presmeruje prihlaseneho uzivatele z anonymnich auth rout
 */
final readonly class AdminAnonymousMiddleware implements MiddlewareInterface
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private CurrentPrincipalProviderInterface $currentPrincipal,
        private AuthenticationService $authentication,
        private Psr17Factory $responses,
        private AdminRoutingConfiguration $routing,
    ) {}

    /**
     * Zpracovava anonymni auth routu podle aktualni identity
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->authentication->hasActiveSession() && $this->currentPrincipal->currentPrincipal() !== null) {
            return $this->responses->createResponse(302)->withHeader('Location', $this->routing->basePath);
        }

        return $handler->handle($request);
    }
}
