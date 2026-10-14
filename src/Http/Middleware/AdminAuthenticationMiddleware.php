<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Middleware;

use Lemonade\Admin\Auth\AdminSessionState;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\InternalAdminDestination;
use Lemonade\Admin\Authorization\CurrentPrincipalProviderInterface;
use Lemonade\Admin\Http\AdminErrorCode;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Kontroluje HTTP pozadavek pred zpracovanim administrace
 */
final readonly class AdminAuthenticationMiddleware implements MiddlewareInterface
{
    private const string INTENDED_PATH = 'admin.auth.intended_path';

    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private CurrentPrincipalProviderInterface $currentPrincipal,
        private AuthenticationService $authentication,
        private SessionInterface $session,
        private Psr17Factory $responses,
        private AdminRoutingConfiguration $routing,
    ) {}

    /**
     * Zpracovava krok process v HTTP toku administrace
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sessionState = $this->authentication->sessionState();
        if ($sessionState === AdminSessionState::ACTIVE && $this->currentPrincipal->currentUser() !== null) {
            return $handler->handle($request);
        }

        if ($sessionState === AdminSessionState::LOCKED && $this->currentPrincipal->currentUser() !== null) {
            if (self::isApiRequest($request, $this->routing)) {
                return self::lockedApiResponse($this->responses);
            }

            return $this->responses->createResponse(302)->withHeader('Location', $this->routing->path('/session/lock') . '?return=' . rawurlencode($this->requestedPath($request)));
        }

        if (self::isApiRequest($request, $this->routing)) {
            return self::unauthenticatedApiResponse($this->responses);
        }

        $this->storeIntendedPath($request);

        return $this->responses->createResponse(302)->withHeader('Location', $this->routing->path('/login'));
    }

    /**
     * Rozpoznava pozadavek, ktery vyzaduje JSON auth kontrakt
     */
    public static function isApiRequest(ServerRequestInterface $request, AdminRoutingConfiguration $routing): bool
    {
        return self::isApiPath($request->getUri()->getPath(), $routing) || self::isJsonRequest($request);
    }

    /**
     * Rozpoznava canonical API nebo AJAX cestu administrace
     */
    private static function isApiPath(string $path, AdminRoutingConfiguration $routing): bool
    {
        $basePath = preg_quote($routing->basePath, '#');

        return $path === $routing->path('/api')
            || str_starts_with($path, $routing->path('/api/'))
            || preg_match('#^' . $basePath . '/[^/]+/ajax(?:/|$)#D', $path) === 1;
    }

    /**
     * Rozpoznava JSON transport mimo canonical API cestu
     */
    private static function isJsonRequest(ServerRequestInterface $request): bool
    {
        return str_contains($request->getHeaderLine('Accept'), 'application/json')
            || str_contains($request->getHeaderLine('X-Requested-With'), 'XMLHttpRequest');
    }

    /**
     * Vraci bezpecnou cestu puvodniho Admin document requestu
     */
    private function requestedPath(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();

        return $query === '' ? $path : $path . '?' . $query;
    }

    /**
     * Zpracovava krok storeintendedpath v HTTP toku administrace
     */
    private function storeIntendedPath(ServerRequestInterface $request): void
    {
        $destination = $this->requestedPath($request);
        if (InternalAdminDestination::isSafe($destination, $this->routing)) {
            $this->session->set(self::INTENDED_PATH, $destination);
        }
    }

    /**
     * Zpracovava krok pullintendedpath v HTTP toku administrace
     */
    public static function pullIntendedPath(SessionInterface $session, AdminRoutingConfiguration $routing): string
    {
        $path = $session->get(self::INTENDED_PATH);
        $session->remove(self::INTENDED_PATH);

        return InternalAdminDestination::isSafe($path, $routing) ? $path : $routing->basePath;
    }

    /**
     * Vytvari sdilenou JSON odpoved pro neautentizovany Admin API pozadavek
     */
    public static function unauthenticatedApiResponse(Psr17Factory $responses): ResponseInterface
    {
        return $responses->createResponse(HttpStatusCode::UNAUTHORIZED->value)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($responses->createStream(json_encode([
                'error' => [
                    'code' => AdminErrorCode::UNAUTHENTICATED->value,
                    'message' => 'Authentication required.',
                ],
            ], JSON_THROW_ON_ERROR)));
    }

    /**
     * Vytvari JSON odpoved pro serverem uzamcenou administracni relaci
     */
    public static function lockedApiResponse(Psr17Factory $responses): ResponseInterface
    {
        return $responses->createResponse(423)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($responses->createStream(json_encode([
                'error' => [
                    'code' => AdminErrorCode::SESSION_LOCKED->value,
                    'message' => 'Session is locked.',
                ],
            ], JSON_THROW_ON_ERROR)));
    }
}
