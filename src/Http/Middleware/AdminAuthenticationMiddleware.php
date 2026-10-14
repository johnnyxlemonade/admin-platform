<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Middleware;

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
        $path = $request->getUri()->getPath();
        if ($this->authentication->hasActiveSession() && $this->currentPrincipal->currentUser() !== null) {
            return $handler->handle($request);
        }

        if ($this->isApiPath($path)) {
            return self::unauthenticatedApiResponse($this->responses);
        }

        $this->storeIntendedPath($request);

        return $this->responses->createResponse(302)->withHeader('Location', $this->routing->path('/login'));
    }

    /**
     * Rozhoduje, zda plati podminka isapipath
     */
    private function isApiPath(string $path): bool
    {
        $basePath = preg_quote($this->routing->basePath, '#');

        return $path === $this->routing->path('/api')
            || str_starts_with($path, $this->routing->path('/api/'))
            || preg_match('#^' . $basePath . '/[^/]+/ajax(?:/|$)#D', $path) === 1;
    }

    /**
     * Zpracovava krok storeintendedpath v HTTP toku administrace
     */
    private function storeIntendedPath(ServerRequestInterface $request): void
    {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        $destination = $query === '' ? $path : $path . '?' . $query;
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
}
