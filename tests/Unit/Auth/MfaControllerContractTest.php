<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use DateTimeImmutable;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\CurrentUserProvider;
use Lemonade\Admin\Auth\Http\Controller\MfaController;
use Lemonade\Admin\Auth\Mfa\AdminMfaConfiguration;
use Lemonade\Admin\Auth\Mfa\MfaAuthenticationService;
use Lemonade\Admin\Auth\Mfa\MfaLoginChallengeStore;
use Lemonade\Admin\Auth\Mfa\MfaRecentPrimaryAuthenticationStore;
use Lemonade\Admin\Auth\Mfa\MfaSecretCipher;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Security\Totp\TotpSecretGenerator;
use Lemonade\Framework\Security\Totp\TotpVerifier;
use Lemonade\Framework\Session\Contract\SessionInterface;
use Lemonade\Framework\View\ViewRendererInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionObject;

final class MfaControllerContractTest extends TestCase
{
    /**
     * Overuje JSON redirect pro vyprsely MFA challenge v auth AJAX transportu
     */
    public function testExpiredChallengeUsesJsonLoginRedirectForAjaxAuthTransport(): void
    {
        $fixture = $this->fixture('optional', false);
        $this->set($fixture['controller'], 'requests', new HttpRequestInspector());

        $response = $fixture['controller']->verifyChallenge(new ServerRequest(
            'POST',
            '/admin/login/mfa',
            ['Accept' => 'application/json'],
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['success' => true, 'redirect' => '/admin/login'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testSelfDisableIsAllowedOnlyForFreshOptionalMfa(): void
    {
        $optional = $this->fixture('optional', true);
        $optional['controller']->disableModal(new ServerRequest('POST', '/admin/mfa/modal/disable'));

        self::assertCount(1, $optional['connection']->statementCalls);
        self::assertStringContainsString('last_revoked_at=?', $optional['connection']->statementCalls[0]);

        $all = $this->fixture('all', true);
        $all['controller']->disableModal(new ServerRequest('POST', '/admin/mfa/modal/disable'));

        self::assertSame([], $all['connection']->statementCalls);
    }

    public function testEnrollmentAndReEnrollmentAreRejectedWithoutFreshPrimaryAuthentication(): void
    {
        $fixture = $this->fixture('optional', false);

        $fixture['controller']->startModalEnrollment(new ServerRequest('POST', '/admin/mfa/modal/enroll/start'));
        $fixture['controller']->confirmModalEnrollment((new ServerRequest('POST', '/admin/mfa/modal/enroll/confirm'))->withParsedBody(['code' => '000000']));

        self::assertSame([], $fixture['connection']->statementCalls);
    }

    /**
     * Returns an informational modal without reading credentials when MFA is disabled
     */
    public function testDisabledMfaReturnsInformationalModal(): void
    {
        $views = self::createMock(ViewRendererInterface::class);
        $views->expects(self::once())
            ->method('content')
            ->with(
                'admin-auth::mfa-modal',
                self::callback(static fn(array $data): bool => $data['enabled'] === false && $data['active'] === false),
            )
            ->willReturn('<div data-mfa-unavailable></div>');
        $fixture = $this->fixture('disabled', false, $views);

        $response = $fixture['controller']->modal(new ServerRequest('GET', '/admin/mfa/modal'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'success' => true,
            'modalHtml' => '<div data-mfa-unavailable></div>',
        ], json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame([], $fixture['connection']->selectCalls);
    }

    /**
     * @return array{controller:MfaController,connection:MfaModalConnectionDouble}
     */
    private function fixture(string $requirement, bool $freshPrimary, ?ViewRendererInterface $views = null): array
    {
        $session = new MfaModalMemorySession();
        $recentPrimary = new MfaRecentPrimaryAuthenticationStore($session);
        if ($freshPrimary) {
            $recentPrimary->remember(42);
        }
        $connection = new MfaModalConnectionDouble();
        $database = new Database($connection, self::createStub(DatabaseDriverInterface::class));
        $configuration = new AdminMfaConfiguration($requirement, 'Admin', base64_encode(str_repeat('a', 32)));
        $credentials = new TotpCredentialService(
            $database,
            new \Lemonade\Admin\Auth\Mfa\TotpCredentialModel($database),
            MfaSecretCipher::fromKey(str_repeat('a', 32)),
            new TotpSecretGenerator(),
            new TotpVerifier($this->clock()),
        );
        $controller = (new ReflectionClass(MfaController::class))->newInstanceWithoutConstructor();
        $this->set($controller, 'configuration', $configuration);
        $this->set($controller, 'currentUser', $this->currentUser($session));
        $this->set($controller, 'recentPrimary', $recentPrimary);
        $this->set($controller, 'credentials', $credentials);
        $this->set($controller, 'mfa', new MfaAuthenticationService($configuration, $credentials, $database, self::createStub(AuditLogWriterInterface::class)));
        $this->set($controller, 'routing', new AdminRoutingConfiguration('/admin'));
        $this->set($controller, 'challenges', new MfaLoginChallengeStore($session, new AdminRoutingConfiguration('/admin')));
        $factory = new Psr17Factory();
        $this->set($controller, 'responses', new Responses(new ResponseBuilder($factory, $factory)));
        if ($views === null) {
            $views = self::createStub(ViewRendererInterface::class);
            $views->method('content')->willReturn('<div></div>');
        }
        $this->set($controller, 'views', $views);

        return ['controller' => $controller, 'connection' => $connection];
    }

    private function currentUser(SessionInterface $session): CurrentUserProvider
    {
        $session->set('admin.auth.user_id', 42);
        $authentication = (new ReflectionClass(AuthenticationService::class))->newInstanceWithoutConstructor();
        $this->set($authentication, 'session', $session);
        $provider = (new ReflectionClass(CurrentUserProvider::class))->newInstanceWithoutConstructor();
        $this->set($provider, 'authentication', $authentication);
        $this->set($provider, 'currentUserResolved', true);
        $this->set($provider, 'resolvedCurrentUser', new AuthenticatedUser(42, 'admin@example.test'));
        $this->set($provider, 'resolvedSessionUserId', 42);

        return $provider;
    }

    private function clock(): ClockInterface
    {
        $clock = self::createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@1767225600'));

        return $clock;
    }

    private function set(object $object, string $property, mixed $value): void
    {
        (new ReflectionObject($object))->getProperty($property)->setValue($object, $value);
    }
}

final class MfaModalConnectionDouble implements ConnectionInterface
{
    /**
     * @var list<string>
     */
    public array $selectCalls = [];

    /** @var list<string> */
    public array $statementCalls = [];

    public function select(string $sql, array $bindings = []): array
    {
        $this->selectCalls[] = $sql;

        return [];
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $this->statementCalls[] = $sql;

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

final class MfaModalMemorySession implements SessionInterface
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

    public function destroy(): void
    {
        $this->clear();
    }

    public function regenerate(bool $deleteOldSession = true): void {}
}
