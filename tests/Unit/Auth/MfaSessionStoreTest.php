<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Mfa\MfaLoginChallengeStore;
use Lemonade\Admin\Auth\Mfa\MfaRecentPrimaryAuthenticationStore;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;
use Lemonade\Framework\Session\Contract\SessionInterface;
use PHPUnit\Framework\TestCase;

final class MfaSessionStoreTest extends TestCase
{
    public function testChallengeStoresOnlyTheDocumentedSafePayloadAndExpires(): void
    {
        $session = $this->session();
        $store = new MfaLoginChallengeStore($session, new AdminRoutingConfiguration('/admin'));
        $store->begin(42, 'verify', 'https://attacker.example');

        self::assertSame(['userId', 'kind', 'issuedAt', 'intendedPath', 'attempts'], array_keys($session->values['admin.auth.mfa.challenge']));
        $challenge = $store->current();
        self::assertNotNull($challenge);
        self::assertNull($challenge['intendedPath']);

        $session->values['admin.auth.mfa.challenge']['issuedAt'] = time() - 301;
        self::assertNull($store->current());
        self::assertArrayNotHasKey('admin.auth.mfa.challenge', $session->values);
    }

    public function testChallengeTerminatesOnItsFifthInvalidAttempt(): void
    {
        $session = $this->session();
        $store = new MfaLoginChallengeStore($session, new AdminRoutingConfiguration('/admin'));
        $store->begin(42, 'verify', '/admin/users');

        for ($attempt = 1; $attempt < 5; $attempt++) {
            self::assertFalse($store->failedAttempt());
        }
        self::assertTrue($store->failedAttempt());
        self::assertNull($store->current());
    }

    public function testRecentPrimaryAuthenticationIsBoundToTheUserAndExpires(): void
    {
        $session = $this->session();
        $store = new MfaRecentPrimaryAuthenticationStore($session);
        $store->remember(42);

        self::assertTrue($store->isRecentFor(42));
        self::assertFalse($store->isRecentFor(43));
        $session->values['admin.auth.mfa.recent_primary']['authenticatedAt'] = time() - 301;
        self::assertFalse($store->isRecentFor(42));
    }

    private function session(): MfaMemorySession
    {
        return new MfaMemorySession();
    }
}

final class MfaMemorySession implements SessionInterface
{
    /** @var array<string,mixed> */
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
}
