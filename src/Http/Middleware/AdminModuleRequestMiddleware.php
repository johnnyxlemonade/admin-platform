<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Middleware;

use Lemonade\Admin\Module\AdminModuleRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Supplies request-local module lookup to the immutable shared route definitions
 */
final readonly class AdminModuleRequestMiddleware implements MiddlewareInterface
{
    /**
     * Receives the module registry of the current request scope without loading it
     */
    public function __construct(private AdminModuleRegistry $modules) {}

    /**
     * Exposes the scoped registry to route predicates without capturing it in the shared router
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->withAttribute(AdminModuleRegistry::class, $this->modules));
    }
}
