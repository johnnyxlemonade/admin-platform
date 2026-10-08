<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Http;

use Lemonade\Admin\Http\Middleware\AdminInstallationGuard;
use Lemonade\Admin\Install\Http\Controller\InstallController;
use Lemonade\Admin\Install\InstallationStateInterface;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Cms\Routing\Locale\PublicLocaleRoutingMiddleware;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Overuje globalni ochranu runtime pred dokonceni instalace
 */
final class AdminInstallationGuardTest extends TestCase
{
    /**
     * Overuje presmerovani verejne homepage do installeru bez spusteni dalsiho runtime
     */
    public function testUninstalledPublicRequestRedirectsBeforeThePublicRuntime(): void
    {
        $state = new FakeInstallationState(rootAccount: false);
        $handler = new CapturingInstallationRequestHandler();

        $response = $this->guard($state)->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/install', $response->getHeaderLine('Location'));
        self::assertNull($handler->request);
        self::assertSame(1, $state->reads());
    }

    /**
     * Overuje installer bypass bez cteni databazoveho installation state
     */
    public function testInstallerRequestBypassesThePublicLocaleRuntimeWithoutStateRead(): void
    {
        $state = new FakeInstallationState(rootAccount: false);
        $handler = new CapturingInstallationRequestHandler();

        $this->guard($state)->process(new ServerRequest('GET', '/admin/install'), $handler);

        self::assertNotNull($handler->request);
        self::assertTrue((bool) $handler->request->getAttribute(PublicLocaleRoutingMiddleware::BYPASS_ATTRIBUTE));
        self::assertSame(0, $state->reads());
    }

    /**
     * Overuje propusteni public runtime po dokoncene instalaci
     */
    public function testInstalledPublicRequestContinuesToThePublicRuntime(): void
    {
        $state = new FakeInstallationState(rootAccount: true);
        $handler = new CapturingInstallationRequestHandler();
        $request = new ServerRequest('GET', '/');

        $this->guard($state)->process($request, $handler);

        self::assertSame($request, $handler->request);
        self::assertSame(1, $state->reads());
    }

    /**
     * Vytvari guard s canonical install route
     */
    private function guard(InstallationStateInterface $state): AdminInstallationGuard
    {
        $router = new Router();
        $router->getNamed(
            name: 'admin.install',
            path: '/admin/install',
            action: ControllerAction::for(InstallController::class, 'index'),
        );

        return new AdminInstallationGuard(
            installation: $state,
            router: $router,
            responses: new Psr17Factory(),
            routing: new AdminRoutingConfiguration('/admin'),
        );
    }
}

/**
 * Poskytuje testovaci canonical stav instalace bez databaze
 */
final class FakeInstallationState implements InstallationStateInterface
{
    private int $reads = 0;

    /**
     * Nastavuje informaci o existenci root uctu
     */
    public function __construct(private readonly bool $rootAccount) {}

    /**
     * Vraci predem nastaveny stav instalace
     *
     * @return array{databaseConnected:bool,databaseSchema:bool,rootAccount:bool}
     */
    public function state(): array
    {
        ++$this->reads;

        return [
            'databaseConnected' => $this->rootAccount,
            'databaseSchema' => $this->rootAccount,
            'rootAccount' => $this->rootAccount,
        ];
    }

    /**
     * Vraci pocet cteni canonical installation state
     */
    public function reads(): int
    {
        return $this->reads;
    }
}

/**
 * Zachycuje request predany dalsimu runtime middleware
 */
final class CapturingInstallationRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $request = null;

    /**
     * Uklada request a vraci prazdnou uspesnou odpoved
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return (new Psr17Factory())->createResponse();
    }
}
