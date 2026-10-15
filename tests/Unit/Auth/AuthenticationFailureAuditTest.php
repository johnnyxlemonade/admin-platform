<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Audit\AuditActorType;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Auth\Http\Controller\LoginController;
use Lemonade\Admin\Auth\Http\Controller\MfaController;
use Lemonade\Admin\Auth\Http\Controller\SessionLockController;
use Lemonade\Admin\Event\DomainEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthenticationFailureAuditTest extends TestCase
{
    /**
     * @param class-string $controllerClass
     * @param array{method:string,reason:string,email?:string} $expectedPayload
     */
    #[DataProvider('failureEvents')]
    public function testFailureAuditUsesAnonymousActorAndMinimalPayload(
        string $controllerClass,
        string $eventCode,
        string $operationCode,
        array $expectedPayload,
        ?string $email,
    ): void {
        $recorded = [];
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::once())
            ->method('record')
            ->willReturnCallback(static function (DomainEvent $event, AuditOperation $operation) use (&$recorded): void {
                $recorded = [$event, $operation];
            });

        $reflection = new ReflectionClass($controllerClass);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('audit')->setValue($controller, $audit);
        $arguments = [$eventCode, $operationCode, $expectedPayload['reason']];
        if ($controllerClass === LoginController::class) {
            $arguments[] = $email;
        }
        $reflection->getMethod('auditFailure')->invokeArgs($controller, $arguments);

        self::assertInstanceOf(DomainEvent::class, $recorded[0]);
        self::assertInstanceOf(AuditOperation::class, $recorded[1]);
        self::assertSame($eventCode, $recorded[0]->code());
        self::assertSame($expectedPayload, $recorded[0]->payload());
        self::assertSame(AuditActorType::Anonymous, $recorded[1]->actor()->type());
        self::assertSame('admin-auth', $recorded[1]->actor()->key());
        self::assertNull($recorded[1]->actor()->userId());
    }

    /**
     * @return iterable<string, array{class-string, string, string, array{method:string,reason:string,email?:string}, string|null}>
     */
    public static function failureEvents(): iterable
    {
        yield 'local login failure' => [LoginController::class, 'system.authentication.login_failed', 'auth.login.failed', ['method' => 'local', 'reason' => 'invalid_credentials', 'email' => 'normalized@example.com'], ' Normalized@Example.COM '];
        yield 'local login throttle' => [LoginController::class, 'system.authentication.login_throttled', 'auth.login.throttled', ['method' => 'local', 'reason' => 'rate_limited', 'email' => 'normalized@example.com'], ' Normalized@Example.COM '];
        yield 'MFA failure' => [MfaController::class, 'system.authentication.mfa_failed', 'auth.mfa.failed', ['method' => 'totp', 'reason' => 'invalid_code'], null];
        yield 'MFA throttle' => [MfaController::class, 'system.authentication.mfa_throttled', 'auth.mfa.throttled', ['method' => 'totp', 'reason' => 'rate_limited'], null];
        yield 'session unlock failure' => [SessionLockController::class, 'system.authentication.session_unlock_failed', 'auth.session_unlock.failed', ['method' => 'local', 'reason' => 'invalid_credentials'], null];
        yield 'session unlock throttle' => [SessionLockController::class, 'system.authentication.session_unlock_throttled', 'auth.session_unlock.throttled', ['method' => 'local', 'reason' => 'rate_limited'], null];
    }

    public function testLoginFailureDoesNotPersistInvalidRawIdentifier(): void
    {
        $recorded = [];
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->method('record')->willReturnCallback(static function (DomainEvent $event) use (&$recorded): void {
            $recorded = $event->payload();
        });
        $reflection = new ReflectionClass(LoginController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('audit')->setValue($controller, $audit);

        $reflection->getMethod('auditFailure')->invoke($controller, 'system.authentication.login_failed', 'auth.login.failed', 'invalid_credentials', 'attacker input');

        self::assertArrayNotHasKey('email', $recorded);
    }
}
