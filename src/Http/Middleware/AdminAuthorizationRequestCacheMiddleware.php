<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Middleware;

use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Authorization\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Kontroluje HTTP pozadavek pred zpracovanim administrace
 */
final class AdminAuthorizationRequestCacheMiddleware implements MiddlewareInterface
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly CurrentUserProvider $currentUser,
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * Zpracovava krok process v HTTP toku administrace
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->currentUser->invalidate();
        $this->authorization->invalidate();

        return $handler->handle($request);
    }
}
