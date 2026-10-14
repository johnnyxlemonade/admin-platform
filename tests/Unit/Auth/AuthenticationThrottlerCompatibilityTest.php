<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\AuthenticationThrottler;
use Lemonade\Admin\Auth\Config\AdminAuthenticationThrottleConfiguration;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\RateLimit\RateLimiterInterface;
use Lemonade\Framework\RateLimit\RateLimitPolicy;
use Lemonade\Framework\RateLimit\RateLimitResult;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Overuje compatibility bypass limiteru pred aplikovanim Admin rate-limit migrace
 */
final class AuthenticationThrottlerCompatibilityTest extends TestCase
{
    /**
     * Overuje, ze chybejici limiter schema neblokuje login status, hit ani clear
     */
    public function testMissingSchemaBypassesAllLoginLimiterOperations(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->expects(self::never())->method('status');
        $limiter->expects(self::never())->method('hit');
        $limiter->expects(self::never())->method('clear');
        $throttler = $this->throttler($limiter, tableExists: false);
        $request = new ServerRequest('POST', '/admin/login');

        $status = $throttler->loginStatus($request, 'root@example.test');
        $failedAttempt = $throttler->failedLogin($request, 'root@example.test');

        self::assertTrue($status->allowed());
        self::assertSame(5, $status->remaining());
        self::assertTrue($failedAttempt->allowed());
        self::assertSame(5, $failedAttempt->remaining());
        $throttler->clearLoginIdentity('root@example.test');
    }

    /**
     * Overuje, ze chybejici limiter schema neblokuje MFA status, hit ani clear
     */
    public function testMissingSchemaBypassesAllMfaLimiterOperations(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->expects(self::never())->method('status');
        $limiter->expects(self::never())->method('hit');
        $limiter->expects(self::never())->method('clear');
        $throttler = $this->throttler($limiter, tableExists: false);
        $request = new ServerRequest('POST', '/admin/login/mfa');

        $status = $throttler->mfaStatus($request, 42);
        $failedAttempt = $throttler->failedMfa($request, 42);

        self::assertTrue($status->allowed());
        self::assertSame(5, $status->remaining());
        self::assertTrue($failedAttempt->allowed());
        self::assertSame(5, $failedAttempt->remaining());
        $throttler->clearMfaUser(42);
    }

    /**
     * Overuje, ze vypnuty throttling necita schema ani nepouziva limiter
     */
    public function testDisabledThrottlingBypassesAllOperationsWithoutStorageDependencies(): void
    {
        $throttler = new AuthenticationThrottler(
            limiter: null,
            configuration: new AdminAuthenticationThrottleConfiguration(enabled: false),
            requests: new HttpRequestInspector(),
            database: null,
        );
        $request = new ServerRequest('POST', '/admin/login');

        self::assertTrue($throttler->loginStatus($request, 'root@example.test')->allowed());
        self::assertTrue($throttler->failedLogin($request, 'root@example.test')->allowed());
        $throttler->clearLoginIdentity('root@example.test');
        self::assertTrue($throttler->mfaStatus($request, 42)->allowed());
        self::assertTrue($throttler->failedMfa($request, 42)->allowed());
        $throttler->clearMfaUser(42);
    }

    /**
     * Overuje, ze po migraci Auth policy pouziva limiter pro login i MFA flow
     */
    public function testPresentSchemaUsesLimiterForLoginAndMfaOperations(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->expects(self::exactly(4))
            ->method('status')
            ->willReturn(new RateLimitResult(true, 5, 0));
        $limiter->expects(self::exactly(4))
            ->method('hit')
            ->willReturn(new RateLimitResult(true, 4, 0));
        $limiter->expects(self::exactly(2))
            ->method('clear');
        $throttler = $this->throttler($limiter);
        $request = new ServerRequest('POST', '/admin/login');

        self::assertTrue($throttler->loginStatus($request, 'root@example.test')->allowed());
        self::assertTrue($throttler->failedLogin($request, 'root@example.test')->allowed());
        $throttler->clearLoginIdentity('root@example.test');
        self::assertTrue($throttler->mfaStatus($request, 42)->allowed());
        self::assertTrue($throttler->failedMfa($request, 42)->allowed());
        $throttler->clearMfaUser(42);
    }

    /**
     * Overuje, ze provozni chyba limiter storage nezmeni fail-closed chovani
     */
    public function testStorageFailureForExistingSchemaIsNotBypassed(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->method('status')->willThrowException(DatabaseException::queryFailed(
            'SELECT attempts, expires_at FROM framework_rate_limit WHERE key_hash = ? LIMIT 1',
            'MySQL server has gone away.',
            new \RuntimeException('MySQL server has gone away.', 2006),
        ));
        $throttler = $this->throttler($limiter);

        $this->expectException(DatabaseException::class);
        $throttler->loginStatus(new ServerRequest('POST', '/admin/login'), 'root@example.test');
    }

    /**
     * Overuje, ze selhani kontroly schema zustane provozni chybou
     */
    public function testTableAvailabilityFailureIsNotBypassed(): void
    {
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('tableExists')->willThrowException(DatabaseException::queryFailed(
            'SELECT 1 FROM information_schema.tables',
            'MySQL server has gone away.',
            new \RuntimeException('MySQL server has gone away.', 2006),
        ));

        $this->expectException(DatabaseException::class);

        new AuthenticationThrottler(
            limiter: $this->createMock(RateLimiterInterface::class),
            configuration: $this->configuration(),
            requests: new HttpRequestInspector(),
            database: new Database($this->createMock(ConnectionInterface::class), $driver),
        );
    }

    /**
     * Vytvari throttler se stejnymi policy jako hostem deklarovana vychozi konfigurace
     */
    private function throttler(RateLimiterInterface $limiter, bool $tableExists = true): AuthenticationThrottler
    {
        return new AuthenticationThrottler(
            limiter: $limiter,
            configuration: $this->configuration(),
            requests: new HttpRequestInspector(),
            database: $this->database($tableExists),
        );
    }

    /**
     * Vytvari database adapter s predem urcenou dostupnosti limiter tabulky
     */
    private function database(bool $tableExists): Database
    {
        $driver = $this->createMock(DatabaseDriverInterface::class);
        $driver->method('tableExists')->with('framework_rate_limit')->willReturn($tableExists);

        return new Database(
            $this->createMock(ConnectionInterface::class),
            $driver,
        );
    }

    /**
     * Vytvari policy odpovidajici hostem deklarovane vychozi konfiguraci
     */
    private function configuration(): AdminAuthenticationThrottleConfiguration
    {
        return new AdminAuthenticationThrottleConfiguration(
            enabled: true,
            loginIp: new RateLimitPolicy(20, 900),
            loginIdentity: new RateLimitPolicy(5, 900),
            mfaIp: new RateLimitPolicy(20, 900),
            mfaIdentity: new RateLimitPolicy(5, 900),
        );
    }
}
