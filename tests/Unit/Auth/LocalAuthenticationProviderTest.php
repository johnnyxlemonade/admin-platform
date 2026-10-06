<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

final class LocalAuthenticationProviderTest extends TestCase
{
    public function testItNormalizesLocalEmailIdentifiers(): void
    {
        self::assertSame('user@local.test', LocalAuthenticationProvider::normalizeEmail(' User@LOCAL.TEST '));
    }

    public function testItCreatesVerifiableLocalPasswordHashes(): void
    {
        $hash = LocalAuthenticationProvider::hashPassword('A-local-test-password');

        self::assertTrue(password_verify('A-local-test-password', $hash));
        self::assertFalse(password_verify('incorrect-password', $hash));
    }

    public function testItAuthenticatesAnActiveLocalUser(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->willReturn([[
            'id' => 42,
            'email' => 'admin@example.test',
            'password_hash' => LocalAuthenticationProvider::hashPassword('correct-password'),
            'active' => 1,
        ]]);

        $attempt = $this->provider($connection)->authenticate(' Admin@Example.Test ', 'correct-password');

        self::assertSame(42, $attempt->user()?->id());
        self::assertSame('admin@example.test', $attempt->user()->email());
        self::assertFalse($attempt->isInactive());
    }

    public function testItRejectsAnIncorrectLocalPassword(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->willReturn([[
            'id' => 42,
            'email' => 'admin@example.test',
            'password_hash' => LocalAuthenticationProvider::hashPassword('correct-password'),
            'active' => 1,
        ]]);

        $attempt = $this->provider($connection)->authenticate('admin@example.test', 'incorrect-password');

        self::assertNull($attempt->user());
        self::assertFalse($attempt->isInactive());
    }

    public function testItMarksAnInactiveLocalUserWithoutAuthenticatingIt(): void
    {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->willReturn([[
            'id' => 42,
            'email' => 'admin@example.test',
            'password_hash' => LocalAuthenticationProvider::hashPassword('correct-password'),
            'active' => 0,
        ]]);

        $attempt = $this->provider($connection)->authenticate('admin@example.test', 'correct-password');

        self::assertNull($attempt->user());
        self::assertTrue($attempt->isInactive());
    }

    public function testItRehashesAnAuthenticatedLocalPasswordWhenTheDefaultChanges(): void
    {
        $password = 'correct-password';
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects(self::once())->method('select')->willReturn([[
            'id' => 42,
            'email' => 'admin@example.test',
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
            'active' => 1,
        ]]);
        $connection->expects(self::once())->method('statement')->with(
            'UPDATE system_user SET password_hash = ?, updated_at = ? WHERE id = ?',
            self::callback(static fn(array $bindings): bool => password_verify($password, (string) $bindings[0]) && $bindings[2] === 42),
        );

        $attempt = $this->provider($connection)->authenticate('admin@example.test', $password);

        self::assertSame(42, $attempt->user()?->id());
    }

    /**
     * Vytvari provider nad kontrolovanym databazovym spojenim
     */
    private function provider(ConnectionInterface $connection): LocalAuthenticationProvider
    {
        return new LocalAuthenticationProvider(new Database(
            $connection,
            $this->createMock(DatabaseDriverInterface::class),
        ));
    }
}
