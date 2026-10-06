<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Middleware;

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
final class AdminAnonymousMiddleware implements MiddlewareInterface
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly CurrentPrincipalProviderInterface $currentPrincipal,
        private readonly Psr17Factory $responses,
        private readonly AdminRoutingConfiguration $routing,
    ) {}

    /**
     * Zpracovava anonymni auth routu podle aktualni identity
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->currentPrincipal->currentPrincipal() !== null) {
            return $this->responses->createResponse(302)->withHeader('Location', $this->routing->basePath);
        }

        return $handler->handle($request);
    }
}
