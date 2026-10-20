<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use DateTimeImmutable;
use Lemonade\Admin\Assets\AdminAssetConfiguration;
use Lemonade\Admin\Assets\AdminAssetManifest;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\AuthenticationThrottler;
use Lemonade\Admin\Auth\AuthPageRenderer;
use Lemonade\Admin\Auth\Config\AdminAuthenticationThrottleConfiguration;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\Http\Controller\SessionLockController;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Localization\ClientTranslationVersion;
use Lemonade\Admin\Presentation\AdminBranding;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Core\Context\ApplicationContextFactory;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Localization\Config\LocalizationUrlConfig;
use Lemonade\Framework\Localization\TranslatorInterface;
use Lemonade\Framework\RateLimit\RateLimiterInterface;
use Lemonade\Framework\RateLimit\RateLimitResult;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\View\ViewRendererInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionObject;

/**
 * Overuje serverovy kontrakt omezeni odemykani uzamcene Admin relace
 */
final class SessionLockControllerTest extends TestCase
{
    /**
     * Overuje 429 odpoved s cekaci dobou v hlavicce i JSON payloadu
     */
    public function testUnlockReturnsRetryDelayWhenLimiterIsActive(): void
    {
        $controller = $this->controller(new RateLimitResult(false, 0, 47));

        $response = $controller->unlock(new ServerRequest('POST', '/admin/api/session/unlock'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('47', $response->getHeaderLine('Retry-After'));
        self::assertSame('throttled', $payload['error']['code']);
        self::assertSame(47, $payload['retryAfterSeconds']);
    }

    /**
     * Overuje read-only zobrazeni limiteru v zamcenem session statusu
     */
    public function testStatusExposesRetryDelayWithoutHittingLimiter(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->expects(self::exactly(2))
            ->method('status')
            ->willReturn(new RateLimitResult(false, 0, 83));
        $limiter->expects(self::never())->method('hit');
        $controller = $this->controllerWithLimiter($limiter);

        $response = $controller->status(new ServerRequest('GET', '/admin/api/session/status'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(423, $response->getStatusCode());
        self::assertSame('local', $payload['unlock']);
        self::assertSame(83, $payload['retryAfterSeconds']);
    }

    /**
     * Overuje predani aktualni cekaci doby do stranky vykreslene po obnoveni
     */
    public function testScreenReceivesRetryDelayForInitialRender(): void
    {
        $controller = $this->controller(new RateLimitResult(false, 0, 125));
        $renderedResponse = $this->createMock(ResponseInterface::class);
        $views = $this->createMock(ViewRendererInterface::class);
        $views->expects(self::once())
            ->method('content')
            ->with(
                'admin-auth::session-lock',
                self::callback(static fn(array $data): bool => $data['retryAfterSeconds'] === 125),
            )
            ->willReturn('<section></section>');
        $views->method('render')->willReturn($renderedResponse);
        $assets = new AdminAssetManifest(
            (new ApplicationContextFactory())->create(sys_get_temp_dir(), ['APP_ENV' => 'testing']),
            new AdminAssetConfiguration('/assets/admin/', 'assets/admin'),
        );
        $this->set($controller, 'pages', new AuthPageRenderer($views, new AdminBranding(), $assets));
        $this->set($controller, 'branding', new AdminBranding());
        $this->set($controller, 'routing', new AdminRoutingConfiguration('/admin'));
        $this->set($controller, 'translationVersion', (new ReflectionClass(ClientTranslationVersion::class))->newInstanceWithoutConstructor());
        $session = $this->session();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('setLocale')->willReturnSelf();
        $this->set($controller, 'uiLocale', new AdminUiLocale(
            new LocalizationConfig('cs', 'cs', ['cs'], new LocalizationUrlConfig(false, '', '', 'locale', false)),
            $session,
            $translator,
        ));

        $response = $controller->screen(new ServerRequest('GET', '/admin/session/lock'));

        self::assertSame($renderedResponse, $response);
    }

    /**
     * Vytvori controller s limiterem vracejicim zadany stav
     */
    private function controller(RateLimitResult $result): SessionLockController
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->method('status')->willReturn($result);

        return $this->controllerWithLimiter($limiter);
    }

    /**
     * Vytvori controller s uzamcenou lokalni relaci a zadanym limiterem
     */
    private function controllerWithLimiter(RateLimiterInterface $limiter): SessionLockController
    {
        $session = $this->session();
        $authentication = (new ReflectionClass(AuthenticationService::class))->newInstanceWithoutConstructor();
        $this->set($authentication, 'session', $session);
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@1700000000'));
        $this->set($authentication, 'clock', $clock);

        $currentUser = (new ReflectionClass(CurrentUserProvider::class))->newInstanceWithoutConstructor();
        $this->set($currentUser, 'authentication', $authentication);
        $this->set($currentUser, 'currentUserResolved', true);
        $this->set($currentUser, 'resolvedCurrentUser', new AuthenticatedUser(42, 'admin@example.test'));
        $this->set($currentUser, 'resolvedSessionUserId', 42);

        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('tableExists')->with('framework_rate_limit')->willReturn(true);
        $throttler = new AuthenticationThrottler(
            limiter: $limiter,
            configuration: AdminAuthenticationThrottleConfiguration::defaults(),
            requests: new HttpRequestInspector(),
            database: new Database($this->createMock(ConnectionInterface::class), $driver),
        );
        $factory = new Psr17Factory();
        $controller = (new ReflectionClass(SessionLockController::class))->newInstanceWithoutConstructor();
        $this->set($controller, 'authentication', $authentication);
        $this->set($controller, 'currentUser', $currentUser);
        $this->set($controller, 'throttler', $throttler);
        $this->set($controller, 'responses', new Responses(new ResponseBuilder($factory, $factory)));

        return $controller;
    }

    /**
     * Vytvori mock relace s identitou a casem v idle lock stavu
     */
    private function session(): SessionInterface
    {
        $values = [
            'admin.auth.user_id' => 42,
            'admin.auth.authenticated_at' => 1_699_990_000,
            'admin.auth.last_activity_at' => 1_699_998_700,
        ];
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed => $values[$key] ?? $default);

        return $session;
    }

    /**
     * Nastavi neinicializovanou zavislost testovaneho readonly objektu
     */
    private function set(object $object, string $property, mixed $value): void
    {
        (new ReflectionObject($object))->getProperty($property)->setValue($object, $value);
    }
}
