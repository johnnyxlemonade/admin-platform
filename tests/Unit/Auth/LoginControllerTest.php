<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use DateTimeImmutable;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\AuthenticationThrottler;
use Lemonade\Admin\Auth\Config\AdminAuthenticationThrottleConfiguration;
use Lemonade\Admin\Auth\Http\Controller\LoginController;
use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Auth\Mfa\AdminMfaConfiguration;
use Lemonade\Admin\Auth\Mfa\MfaAuthenticationService;
use Lemonade\Admin\Auth\Mfa\MfaLoginChallengeStore;
use Lemonade\Admin\Auth\Mfa\MfaSecretCipher;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Admin\Localization\AdminUiLocale;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Clock\ClockInterface;
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
use Lemonade\Framework\RateLimit\RateLimitPolicy;
use Lemonade\Framework\RateLimit\RateLimitResult;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Security\Csrf\CsrfViewHelper;
use Lemonade\Framework\Security\Totp\TotpSecretGenerator;
use Lemonade\Framework\Security\Totp\TotpVerifier;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionObject;

final class LoginControllerTest extends TestCase
{
    /**
     * Overuje zobrazeni zaokrouhleneho retry casu v JSON throttle odpovedi
     */
    #[DataProvider('retryTimeCases')]
    public function testThrottledLoginReturnsLocalizedRetryTime(int $retryAfterSeconds, string $message): void
    {
        $session = new LoginControllerMemorySession();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('setLocale')->willReturnSelf();
        $translator->method('get')->willReturnCallback(static function (string $key, array $replacements = []): string {
            return match ($key) {
                'auth.errors.throttled_seconds' => 'Try again in ' . $replacements['seconds'] . ' seconds.',
                'auth.errors.throttled_minutes' => 'Try again in ' . $replacements['minutes'] . ' minutes.',
                default => $key,
            };
        });
        $factory = new Psr17Factory();
        $controller = (new ReflectionClass(LoginController::class))->newInstanceWithoutConstructor();
        $this->set($controller, 'throttler', $this->throttler(new RateLimitResult(false, 0, $retryAfterSeconds)));
        $this->set($controller, 'uiLocale', $this->locale($session, $translator));
        $this->set($controller, 'translator', $translator);
        $this->set($controller, 'csrf', new CsrfViewHelper(new CsrfTokenManager($session)));
        $this->set($controller, 'responses', new Responses(new ResponseBuilder($factory, $factory)));
        $this->set($controller, 'requests', new HttpRequestInspector());

        $response = $controller->login((new ServerRequest('POST', '/admin/login', ['Accept' => 'application/json']))->withParsedBody([
            'email' => 'dev-root@ddev.site',
            'password' => 'dev-root@ddev.site',
        ]));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame((string) $retryAfterSeconds, $response->getHeaderLine('Retry-After'));
        self::assertSame($message, json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)['message']);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function retryTimeCases(): iterable
    {
        yield 'seconds' => [59, 'Try again in 59 seconds.'];
        yield 'rounded minutes' => [61, 'Try again in 2 minutes.'];
    }

    public function testJsonLoginReturnsMfaChallengeRedirectInsteadOfHtmlRedirect(): void
    {
        $session = new LoginControllerMemorySession();
        $connection = new LoginControllerConnectionDouble();
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $authentication = new AuthenticationService(
            new LocalAuthenticationProvider($database),
            $session,
            $database,
            new CsrfTokenManager($session),
            $this->createMock(AuditLogWriterInterface::class),
        );
        $credentials = new TotpCredentialService(
            $database,
            new \Lemonade\Admin\Auth\Mfa\TotpCredentialModel($database),
            MfaSecretCipher::fromKey(str_repeat('a', 32)),
            new TotpSecretGenerator(),
            new TotpVerifier($this->clock()),
        );
        $routing = new AdminRoutingConfiguration('/admin');
        $controller = (new ReflectionClass(LoginController::class))->newInstanceWithoutConstructor();
        $this->set($controller, 'authentication', $authentication);
        $this->set($controller, 'throttler', $this->throttler());
        $this->set($controller, 'mfa', new MfaAuthenticationService(
            new AdminMfaConfiguration('optional', 'Admin', base64_encode(str_repeat('a', 32))),
            $credentials,
            $database,
            $this->createMock(AuditLogWriterInterface::class),
        ));
        $this->set($controller, 'mfaChallenges', new MfaLoginChallengeStore($session, $routing));
        $this->set($controller, 'uiLocale', $this->locale($session));
        $this->set($controller, 'session', $session);
        $this->set($controller, 'routing', $routing);
        $factory = new Psr17Factory();
        $this->set($controller, 'responses', new Responses(new ResponseBuilder($factory, $factory)));
        $this->set($controller, 'requests', new HttpRequestInspector());

        $response = $controller->login((new ServerRequest('POST', '/admin/login', ['Accept' => 'application/json']))->withParsedBody([
            'email' => 'dev-root@ddev.site',
            'password' => 'dev-root@ddev.site',
        ]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true, 'redirect' => '/admin/login/mfa'], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function locale(SessionInterface $session, ?TranslatorInterface $translator = null): AdminUiLocale
    {
        if ($translator === null) {
            $translator = $this->createMock(TranslatorInterface::class);
            $translator->method('setLocale')->willReturnSelf();
        }

        return new AdminUiLocale(
            new LocalizationConfig('cs', 'cs', ['cs'], new LocalizationUrlConfig(false, '', '', 'locale', false)),
            $session,
            $translator,
        );
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@1767225600'));

        return $clock;
    }

    private function throttler(?RateLimitResult $status = null): AuthenticationThrottler
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->method('status')->willReturn($status ?? new RateLimitResult(true, 20, 0));
        $limiter->method('hit')->willReturn(new RateLimitResult(true, 20, 0));
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('tableExists')->with('framework_rate_limit')->willReturn(true);

        return new AuthenticationThrottler(
            limiter: $limiter,
            configuration: new AdminAuthenticationThrottleConfiguration(
                enabled: true,
                loginIp: new RateLimitPolicy(20, 900),
                loginIdentity: new RateLimitPolicy(5, 900),
                mfaIp: new RateLimitPolicy(20, 900),
                mfaIdentity: new RateLimitPolicy(5, 900),
            ),
            requests: new HttpRequestInspector(),
            database: new Database($this->createMock(ConnectionInterface::class), $driver),
        );
    }

    private function set(object $object, string $property, mixed $value): void
    {
        (new ReflectionObject($object))->getProperty($property)->setValue($object, $value);
    }
}

final class LoginControllerConnectionDouble implements ConnectionInterface
{
    public function select(string $sql, array $bindings = []): array
    {
        if (str_contains($sql, 'FROM system_user WHERE email')) {
            return [[
                'id' => 42,
                'email' => 'dev-root@ddev.site',
                'password_hash' => password_hash('dev-root@ddev.site', PASSWORD_DEFAULT),
                'active' => 1,
            ]];
        }

        if (str_contains($sql, 'FROM system_user_totp')) {
            return [[
                'secret_ciphertext' => 'active-secret',
                'enabled_at' => '2026-01-01 00:00:00',
                'enrollment_expires_at' => null,
            ]];
        }

        return [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        return 1;
    }

    public function inTransaction(): bool
    {
        return false;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollBack(): void {}

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    public function cursor(string $sql, array $bindings = []): \Generator
    {
        yield from [];
    }

    public function lastInsertId(): int|string|null
    {
        return null;
    }

    public function affectedRows(): int
    {
        return 1;
    }

    public function reconnect(): void {}

    public function close(): void {}

    public function serverVersion(): string
    {
        return '';
    }

    public function escapeString(string $value): string
    {
        return $value;
    }
}

final class LoginControllerMemorySession implements SessionInterface
{
    /** @var array<string,mixed> */
    private array $values = [];

    public function start(): void {}

    public function started(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function regenerate(bool $deleteOldSession = true): void {}
}
