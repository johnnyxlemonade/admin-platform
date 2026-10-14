<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Doklada kratke cerstve overeni hesla jen pro self-service MFA mutace
 */
final readonly class MfaRecentPrimaryAuthenticationStore
{
    private const string SESSION_KEY = 'admin.auth.mfa.recent_primary';
    private const int TTL_SECONDS = 300;

    public function __construct(private readonly SessionInterface $session) {}

    /**
     * Zapamatuje cas uspesneho lokalniho overeni hesla
     */
    public function remember(int $userId): void
    {
        $this->session->set(self::SESSION_KEY, ['userId' => $userId, 'authenticatedAt' => time()]);
    }

    /**
     * Overi vazbu na aktualniho uzivatele a petiminutovou platnost
     */
    public function isRecentFor(int $userId): bool
    {
        $value = $this->session->get(self::SESSION_KEY);
        return is_array($value)
            && ($value['userId'] ?? null) === $userId
            && is_int($value['authenticatedAt'] ?? null)
            && time() - $value['authenticatedAt'] <= self::TTL_SECONDS;
    }

    /**
     * Odstrani cerstve overeni pri odhlaseni nebo zmene identity
     */
    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }
}
