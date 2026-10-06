<?php

declare(strict_types=1);

namespace Lemonade\Admin\Http\Middleware;

use Lemonade\Admin\Install\InstallationService;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Http\HttpStatus as HttpStatusCode;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Zpracovava HTTP podporu administrace
 */
final class AdminInstallationGuard implements MiddlewareInterface
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani pozadavku
     */
    public function __construct(
        private readonly InstallationService $installation,
        private readonly Router $router,
        private readonly Psr17Factory $responses,
        private readonly AdminRoutingConfiguration $routing,
    ) {}

    /**
     * Zpracovava krok process v HTTP toku administrace
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!$this->isAdminPath($path) || $this->isInstallerPath($path) || $this->installation->state()['rootAccount']) {
            return $handler->handle($request);
        }

        $installerUrl = $this->router->url('admin.install');
        if ($this->isStructuredRequest($request)) {
            return $this->jsonRequiredResponse($installerUrl);
        }

        return $this->responses->createResponse(302)->withHeader('Location', $installerUrl);
    }

    /**
     * Zpracovava krok jsonrequiredresponse v HTTP toku administrace
     */
    private function jsonRequiredResponse(string $installerUrl): ResponseInterface
    {
        return $this->responses->createResponse(HttpStatusCode::SERVICE_UNAVAILABLE->value)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->responses->createStream(json_encode([
                'success' => false,
                'error' => [
                    'code' => 'installation_required',
                    'message' => 'Application installation is required.',
                    'installerUrl' => $installerUrl,
                ],
                'installerUrl' => $installerUrl,
            ], JSON_THROW_ON_ERROR)));
    }

    /**
     * Rozhoduje, zda plati podminka isadminpath
     */
    private function isAdminPath(string $path): bool
    {
        return $this->routing->contains($path);
    }

    /**
     * Rozhoduje, zda plati podminka isinstallerpath
     */
    private function isInstallerPath(string $path): bool
    {
        return $path === $this->routing->path('/install') || str_starts_with($path, $this->routing->path('/install/'));
    }

    /**
     * Rozhoduje, zda plati podminka isstructuredrequest
     */
    private function isStructuredRequest(ServerRequestInterface $request): bool
    {
        $method = strtoupper($request->getMethod());
        $accept = strtolower($request->getHeaderLine('Accept'));
        $requestedWith = strtolower($request->getHeaderLine('X-Requested-With'));

        return !in_array($method, ['GET', 'HEAD'], true)
            || str_contains($accept, 'application/json')
            || $requestedWith === 'xmlhttprequest';
    }
}
