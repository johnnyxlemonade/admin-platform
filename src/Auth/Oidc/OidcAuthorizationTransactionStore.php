<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Uklada rozpracovane OIDC prihlaseni
 */
final class OidcAuthorizationTransactionStore
{
    private const SESSION_KEY = 'admin.auth.oidc.transactions';
    private const TTL_SECONDS = 600;

    public function __construct(private readonly SessionInterface $session) {}

    public function begin(string $providerKey, ?string $intendedPath = null, ?int $now = null): OidcAuthorizationTransaction
    {
        $transaction = new OidcAuthorizationTransaction(
            $providerKey,
            $this->randomValue(),
            $this->randomValue(),
            $this->randomValue(),
            $now ?? time(),
            $intendedPath,
        );
        $transactions = $this->transactions();
        $transactions[$providerKey] = $transaction->toArray();
        $this->session->set(self::SESSION_KEY, $transactions);

        return $transaction;
    }

    public function consume(string $providerKey, string $state, ?int $now = null): OidcAuthorizationTransaction
    {
        $transactions = $this->transactions();
        $rawTransaction = $transactions[$providerKey] ?? null;
        if (!is_array($rawTransaction)) {
            throw new OidcProtocolException('authorization_transaction_missing');
        }
        $transaction = OidcAuthorizationTransaction::fromArray($rawTransaction);
        if ($transaction === null || $transaction->providerKey() !== $providerKey) {
            unset($transactions[$providerKey]);
            $this->session->set(self::SESSION_KEY, $transactions);
            throw new OidcProtocolException('authorization_transaction_invalid');
        }
        if ($transaction->isExpired($now ?? time(), self::TTL_SECONDS)) {
            unset($transactions[$providerKey]);
            $this->session->set(self::SESSION_KEY, $transactions);
            throw new OidcProtocolException('authorization_transaction_expired');
        }
        if (!hash_equals($transaction->state(), $state)) {
            throw new OidcProtocolException('authorization_state_invalid');
        }

        unset($transactions[$providerKey]);
        $this->session->set(self::SESSION_KEY, $transactions);

        return $transaction;
    }

    /** @return array<string, array<string, mixed>> */
    private function transactions(): array
    {
        $value = $this->session->get(self::SESSION_KEY, []);

        return is_array($value) ? $value : [];
    }

    private function randomValue(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
