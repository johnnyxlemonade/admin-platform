<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Http\Controller;

use Lemonade\Admin\Auth\AdminSessionState;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Http\Middleware\AdminAuthenticationMiddleware;
use Lemonade\Framework\Http\HttpStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * Obsluhuje explicitni heartbeat aktivni administracni relace
 */
final readonly class SessionHeartbeatController
{
    /**
     * Nastavuje sluzby pro aktualizaci idle aktivity relace
     */
    public function __construct(
        private AuthenticationService $authentication,
        private Psr17Factory $responses,
    ) {}

    /**
     * Posune idle aktivitu relace overene authentication middlewarem
     */
    public function heartbeat(): ResponseInterface
    {
        if (!$this->authentication->heartbeat()) {
            if ($this->authentication->sessionState() === AdminSessionState::LOCKED) {
                return AdminAuthenticationMiddleware::lockedApiResponse($this->responses);
            }

            return AdminAuthenticationMiddleware::unauthenticatedApiResponse($this->responses);
        }

        return $this->responses->createResponse(HttpStatus::NO_CONTENT->value);
    }
}
