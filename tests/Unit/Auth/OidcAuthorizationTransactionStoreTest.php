<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Oidc\OidcAuthorizationTransactionStore;
use Lemonade\Admin\Auth\Oidc\OidcProtocolException;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;

final class OidcAuthorizationTransactionStoreTest extends TestCase
{
    public function testItConsumesAProviderScopedTransactionOnlyOnce(): void
    {
        $session = new class implements SessionInterface {
            /** @var array<string, mixed> */
            public array $values = [];

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
        };
        $store = new OidcAuthorizationTransactionStore($session);
        $transaction = $store->begin('provider-a', '/admin/users', 1000);

        $consumed = $store->consume('provider-a', $transaction->state(), 1001);

        self::assertSame($transaction->nonce(), $consumed->nonce());
        self::assertSame('/admin/users', $consumed->intendedPath());
        $this->expectException(OidcProtocolException::class);
        $store->consume('provider-a', $transaction->state(), 1002);
    }

    public function testItRejectsStateMismatchAndExpiredTransactions(): void
    {
        $session = $this->session();
        $store = new OidcAuthorizationTransactionStore($session);
        $transaction = $store->begin('provider-a', null, 1000);

        try {
            $store->consume('provider-a', 'incorrect-state', 1001);
            self::fail('Expected state mismatch.');
        } catch (OidcProtocolException $exception) {
            self::assertSame('authorization_state_invalid', $exception->getMessage());
        }
        try {
            $store->consume('provider-a', $transaction->state(), 1601);
            self::fail('Expected expired transaction.');
        } catch (OidcProtocolException $exception) {
            self::assertSame('authorization_transaction_expired', $exception->getMessage());
        }
    }

    public function testItDoesNotConsumeAnotherProvidersTransaction(): void
    {
        $store = new OidcAuthorizationTransactionStore($this->session());
        $transaction = $store->begin('provider-a', null, 1000);

        try {
            $store->consume('other-provider', $transaction->state(), 1001);
            self::fail('Expected provider-isolated transaction lookup to fail.');
        } catch (OidcProtocolException $exception) {
            self::assertSame('authorization_transaction_missing', $exception->getMessage());
        }

        self::assertSame('provider-a', $store->consume('provider-a', $transaction->state(), 1001)->providerKey());
    }

    private function session(): SessionInterface
    {
        return new class implements SessionInterface {
            /** @var array<string, mixed> */
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
        };
    }
}
