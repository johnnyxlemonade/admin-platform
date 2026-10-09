<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use DateTimeImmutable;
use Lemonade\Admin\Auth\Mfa\MfaSecretCipher;
use Lemonade\Admin\Auth\Mfa\TotpCredentialService;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Security\Totp\TotpSecretGenerator;
use Lemonade\Framework\Security\Totp\TotpVerifier;
use PHPUnit\Framework\TestCase;

final class TotpCredentialServiceContractTest extends TestCase
{
    public function testItLocksCredentialAndRejectsAConsumedOrOlderTimeStep(): void
    {
        $cipher = new MfaSecretCipher(str_repeat('a', 32));
        $connection = $this->connection([[
            'secret_ciphertext' => $cipher->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 42),
            'enabled_at' => '2026-01-01 00:00:00',
            'last_consumed_time_step' => 1,
            'enrollment_expires_at' => null,
        ]]);
        $service = $this->service($connection, $cipher, 59);

        self::assertFalse($service->verifyAndConsume(42, '287082'));
        self::assertStringContainsString('FOR UPDATE', $connection->selectCalls[0]);
    }

    public function testEnrollmentConfirmationConsumesItsMatchingStep(): void
    {
        $cipher = new MfaSecretCipher(str_repeat('a', 32));
        $connection = $this->connection([[
            'pending_secret_ciphertext' => $cipher->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 42),
            'enrollment_expires_at' => '2099-01-01 00:00:00',
        ]]);
        self::assertTrue($this->service($connection, $cipher, 59)->confirmEnrollment(42, '287082'));
    }

    public function testPendingEnrollmentExpiresTenMinutesAfterItStarts(): void
    {
        $connection = $this->connection([]);
        $before = time();

        $this->service($connection, new MfaSecretCipher(str_repeat('a', 32)), 59)->beginEnrollment(42);

        $bindings = $connection->statementBindings[0];
        self::assertSame($before + 600, strtotime((string) $bindings[3]));
    }

    public function testReEnrollmentKeepsTheActiveCredentialUntilTheNewSecretIsConfirmed(): void
    {
        $cipher = new MfaSecretCipher(str_repeat('a', 32));
        $connection = $this->connection([[
            'secret_ciphertext' => $cipher->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 42),
            'enabled_at' => '2026-01-01 00:00:00',
            'pending_secret_ciphertext' => null,
            'enrollment_expires_at' => null,
        ]]);
        $service = $this->service($connection, $cipher, 59);

        $service->beginEnrollment(42);

        self::assertStringNotContainsString('secret_ciphertext=NULL', $connection->statementCalls[0]);

        $confirmationConnection = $this->connection([[
            'secret_ciphertext' => $cipher->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 42),
            'enabled_at' => '2026-01-01 00:00:00',
            'pending_secret_ciphertext' => $cipher->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 42),
            'enrollment_expires_at' => '2099-01-01 00:00:00',
        ]]);

        self::assertTrue($this->service($confirmationConnection, $cipher, 59)->confirmEnrollment(42, '287082'));
        self::assertStringContainsString('secret_ciphertext=pending_secret_ciphertext', $confirmationConnection->statementCalls[0]);
    }

    public function testEnrollmentConfirmationPreventsTheSameTimeStepFromCompletingTheNextLogin(): void
    {
        $cipher = new MfaSecretCipher(str_repeat('a', 32));
        $connection = $this->connection([[
            'secret_ciphertext' => null,
            'pending_secret_ciphertext' => $cipher->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 42),
            'enabled_at' => null,
            'enrollment_expires_at' => '2099-01-01 00:00:00',
            'last_consumed_time_step' => null,
        ]]);
        $service = $this->service($connection, $cipher, 59);

        self::assertTrue($service->confirmEnrollment(42, '287082'));
        self::assertFalse($service->verifyAndConsume(42, '287082'));
    }

    public function testExpiredPendingEnrollmentIsLazilyClearedWithoutActivatingCredential(): void
    {
        $cipher = new MfaSecretCipher(str_repeat('a', 32));
        $connection = $this->connection([[
            'secret_ciphertext' => null,
            'pending_secret_ciphertext' => $cipher->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 42),
            'enabled_at' => null,
            'enrollment_expires_at' => '2000-01-01 00:00:00',
        ]]);

        self::assertFalse($this->service($connection, $cipher, 59)->hasActiveCredential(42));
        self::assertStringContainsString('pending_secret_ciphertext=NULL', $connection->statementCalls[0]);
    }

    public function testRevokeClearsAllCredentialStatesAndRecordsTheRevocationTime(): void
    {
        $connection = $this->connection([]);

        $this->service($connection, new MfaSecretCipher(str_repeat('a', 32)), 59)->revoke(42);

        self::assertStringContainsString('secret_ciphertext=NULL', $connection->statementCalls[0]);
        self::assertStringContainsString('pending_secret_ciphertext=NULL', $connection->statementCalls[0]);
        self::assertStringContainsString('last_consumed_time_step=NULL', $connection->statementCalls[0]);
        self::assertStringContainsString('last_revoked_at=?', $connection->statementCalls[0]);
        self::assertNotNull($connection->statementBindings[0][0]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function connection(array $rows): TotpConnectionDouble
    {
        return new TotpConnectionDouble($rows);
    }

    private function service(ConnectionInterface $connection, MfaSecretCipher $cipher, int $timestamp): TotpCredentialService
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('@' . $timestamp));

        return new TotpCredentialService(
            new Database($connection, $this->createMock(DatabaseDriverInterface::class)),
            $cipher,
            new TotpSecretGenerator(),
            new TotpVerifier($clock),
        );
    }
}

final class TotpConnectionDouble implements ConnectionInterface
{
    /** @var list<string> */
    public array $selectCalls = [];

    /** @var list<string> */
    public array $statementCalls = [];

    /** @var list<array<array-key,mixed>> */
    public array $statementBindings = [];

    /** @param list<array<string,mixed>> $rows */
    public function __construct(private array $rows) {}

    public function select(string $sql, array $bindings = []): array
    {
        $this->selectCalls[] = $sql;

        return $this->rows;
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $this->statementCalls[] = $sql;
        $this->statementBindings[] = $bindings;

        if (str_starts_with($sql, 'INSERT INTO system_user_totp')) {
            if ($this->rows === []) {
                $this->rows[] = [];
            }
            $this->rows[0]['pending_secret_ciphertext'] = $bindings[1];
            $this->rows[0]['enrollment_expires_at'] = $bindings[3];
        }

        if (str_contains($sql, 'secret_ciphertext=pending_secret_ciphertext')) {
            $this->rows[0]['secret_ciphertext'] = $this->rows[0]['pending_secret_ciphertext'];
            $this->rows[0]['pending_secret_ciphertext'] = null;
            $this->rows[0]['enabled_at'] = $bindings[0];
            $this->rows[0]['enrollment_expires_at'] = null;
            $this->rows[0]['last_consumed_time_step'] = $bindings[1];
        }

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
