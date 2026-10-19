<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use DateTimeImmutable;
use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Auth\Mfa\AdminMfaConfiguration;
use Lemonade\Admin\Auth\Mfa\MfaAuthenticationService;
use Lemonade\Admin\Auth\Mfa\MfaSecretCipher;
use Lemonade\Admin\Auth\Mfa\TotpCredentialModel;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Security\Totp\TotpSecretGenerator;
use Lemonade\Framework\Security\Totp\TotpVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MfaAuthenticationServiceTest extends TestCase
{
    #[DataProvider('policyMatrix')]
    public function testItSelectsTheApprovedLocalMfaPolicy(string $requirement, bool $activeCredential, string $expected): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(static function () use ($activeCredential): array {
            return $activeCredential ? [[
                'secret_ciphertext' => 'active',
                'enabled_at' => '2026-01-01 00:00:00',
                'enrollment_expires_at' => null,
            ]] : [];
        });
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $credentials = new TotpCredentialService(
            $database,
            new TotpCredentialModel($database),
            MfaSecretCipher::fromKey(str_repeat('a', 32)),
            new TotpSecretGenerator(),
            new TotpVerifier($this->clock()),
        );
        $service = new MfaAuthenticationService(
            new AdminMfaConfiguration($requirement, $requirement === 'disabled' ? null : 'Admin', $requirement === 'disabled' ? null : base64_encode(str_repeat('a', 32))),
            $credentials,
            $database,
            $this->createMock(AuditLogWriterInterface::class),
        );

        self::assertSame($expected, $service->loginRequirement(new AuthenticatedUser(42, 'admin@example.test')));
    }

    /**
     * @return iterable<string,array{string,bool,string}>
     */
    public static function policyMatrix(): iterable
    {
        yield 'disabled' => ['disabled', false, 'login'];
        yield 'optional without credential' => ['optional', false, 'login'];
        yield 'optional with credential' => ['optional', true, 'challenge'];
        yield 'all without credential' => ['all', false, 'enroll'];
        yield 'all with credential' => ['all', true, 'challenge'];
    }

    public function testItRecordsOnlyAllowlistedMfaAuditMetadata(): void
    {
        $events = [];
        $audit = $this->createMock(AuditLogWriterInterface::class);
        $audit->expects(self::exactly(4))->method('record')->willReturnCallback(
            static function (DomainEvent $event, AuditActor $actor) use (&$events): void {
                $events[$event->code()] = [$event->payload(), $actor->userId()];
            },
        );
        $connection = $this->createMock(ConnectionInterface::class);
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $credentials = new TotpCredentialService($database, new TotpCredentialModel($database), MfaSecretCipher::fromKey(str_repeat('a', 32)), new TotpSecretGenerator(), new TotpVerifier($this->clock()));
        $service = new MfaAuthenticationService(new AdminMfaConfiguration('optional', 'Admin', base64_encode(str_repeat('a', 32))), $credentials, $database, $audit);

        $service->enrolled(42);
        $service->disabled(42);
        $service->reset(7, 42);
        $service->challengeFailed(42);

        self::assertSame([], $events['system.authentication.mfa_enrolled'][0]);
        self::assertSame([], $events['system.authentication.mfa_disabled'][0]);
        self::assertSame([], $events['system.authentication.mfa_reset'][0]);
        self::assertSame([], $events['system.authentication.mfa_challenge_failed'][0]);
        self::assertSame(7, $events['system.authentication.mfa_reset'][1]);
    }

    public function testPendingLoginRejectsDisabledAndDeletedUsersAtTheFinalEligibilityCheck(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::exactly(2))->method('select')->willReturn([]);
        $database = new Database($connection, $this->createMock(DatabaseDriverInterface::class));
        $credentials = new TotpCredentialService($database, new TotpCredentialModel($database), MfaSecretCipher::fromKey(str_repeat('a', 32)), new TotpSecretGenerator(), new TotpVerifier($this->clock()));
        $service = new MfaAuthenticationService(new AdminMfaConfiguration('all', 'Admin', base64_encode(str_repeat('a', 32))), $credentials, $database, $this->createMock(AuditLogWriterInterface::class));

        self::assertNull($service->eligibleUser(42));
        self::assertNull($service->eligibleUser(43));
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@1767225600'));

        return $clock;
    }
}
