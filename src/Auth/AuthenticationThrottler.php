<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Auth\Config\AdminAuthenticationThrottleConfiguration;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\RateLimit\RateLimiterInterface;
use Lemonade\Framework\RateLimit\RateLimitPolicy;
use Lemonade\Framework\RateLimit\RateLimitResult;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Aplikuje Admin policy nad generic fixed-window rate limiterem
 */
final readonly class AuthenticationThrottler
{
    private const string LOGIN_IP_SCOPE = 'admin-auth.login.ip';
    private const string LOGIN_IDENTITY_SCOPE = 'admin-auth.login.identity';
    private const string MFA_IP_SCOPE = 'admin-auth.mfa.ip';
    private const string MFA_USER_SCOPE = 'admin-auth.mfa.user';

    /**
     * Uchovava jednou zjistenou dostupnost limiter tabulky pri zapnutem throttlingu
     */
    private bool $rateLimitTableExists;

    /**
     * Nastavuje volitelny limiter, konfiguraci policy a canonical resolver klienta
     */
    public function __construct(
        private ?RateLimiterInterface                    $limiter,
        private AdminAuthenticationThrottleConfiguration $configuration,
        private HttpRequestInspector                     $requests,
        ?Database                                        $database,
    ) {
        if (!$this->configuration->hasEnabledBuckets()) {
            $this->rateLimitTableExists = false;

            return;
        }

        if ($this->limiter === null || $database === null) {
            throw new \LogicException('Enabled Admin authentication throttling requires a limiter and database.');
        }

        $this->rateLimitTableExists = $database->tableExists('framework_rate_limit');
    }

    /**
     * Vraci stav IP a identity bucketu pred overenim lokalniho hesla
     */
    public function loginStatus(ServerRequestInterface $request, string $identifier): RateLimitResult
    {
        if (!$this->configuration->hasEnabledBuckets()) {
            return $this->compatibilityBypassResult();
        }

        if (!$this->rateLimitTableExists) {
            return $this->compatibilityBypassResult($this->configuration->loginIp(), $this->configuration->loginIdentity());
        }

        return $this->decision($this->loginResults($request, $identifier, false));
    }

    /**
     * Zaznamena neuspesny lokalni login do IP i identity bucketu
     */
    public function failedLogin(ServerRequestInterface $request, string $identifier): RateLimitResult
    {
        if (!$this->configuration->hasEnabledBuckets()) {
            return $this->compatibilityBypassResult();
        }

        if (!$this->rateLimitTableExists) {
            return $this->compatibilityBypassResult($this->configuration->loginIp(), $this->configuration->loginIdentity());
        }

        return $this->decision($this->loginResults($request, $identifier, true));
    }

    /**
     * Odstrani identity bucket po uspesnem lokalnim overeni hesla
     */
    public function clearLoginIdentity(string $identifier): void
    {
        if ($this->configuration->loginIdentityEnabled() && $this->rateLimitTableExists) {
            $this->limiter()->clear($this->loginIdentityKey($identifier));
        }
    }

    /**
     * Vraci stav IP a user bucketu pred overenim MFA kodu
     */
    public function mfaStatus(ServerRequestInterface $request, int $userId): RateLimitResult
    {
        if (!$this->configuration->hasEnabledBuckets()) {
            return $this->compatibilityBypassResult();
        }

        if (!$this->rateLimitTableExists) {
            return $this->compatibilityBypassResult($this->configuration->mfaIp(), $this->configuration->mfaIdentity());
        }

        return $this->decision($this->mfaResults($request, $userId, false));
    }

    /**
     * Zaznamena neuspesny MFA kod do IP i user bucketu
     */
    public function failedMfa(ServerRequestInterface $request, int $userId): RateLimitResult
    {
        if (!$this->configuration->hasEnabledBuckets()) {
            return $this->compatibilityBypassResult();
        }

        if (!$this->rateLimitTableExists) {
            return $this->compatibilityBypassResult($this->configuration->mfaIp(), $this->configuration->mfaIdentity());
        }

        return $this->decision($this->mfaResults($request, $userId, true));
    }

    /**
     * Odstrani user bucket po uspesnem dokonceni MFA challenge
     */
    public function clearMfaUser(int $userId): void
    {
        if ($this->configuration->mfaIdentityEnabled() && $this->rateLimitTableExists) {
            $this->limiter()->clear($this->mfaUserKey($userId));
        }
    }

    /**
     * Vytvori allowed vysledek odpovidajici prazdnym bucketum pred aplikovanim migrace
     */
    private function compatibilityBypassResult(RateLimitPolicy ...$policies): RateLimitResult
    {
        $remaining = PHP_INT_MAX;

        foreach ($policies as $policy) {
            $remaining = min($remaining, $policy->attempts());
        }

        return new RateLimitResult(
            allowed: true,
            remaining: $remaining === PHP_INT_MAX ? 0 : $remaining,
            retryAfterSeconds: 0,
        );
    }

    /**
     * Vrati status nebo hit pouze pro zapnute login buckety
     *
     * @return list<RateLimitResult>
     */
    private function loginResults(ServerRequestInterface $request, string $identifier, bool $hit): array
    {
        $results = [];
        if ($this->configuration->loginIpEnabled()) {
            $results[] = $hit
                ? $this->limiter()->hit($this->loginIpKey($request), self::LOGIN_IP_SCOPE, $this->configuration->loginIp())
                : $this->limiter()->status($this->loginIpKey($request), self::LOGIN_IP_SCOPE, $this->configuration->loginIp());
        }
        if ($this->configuration->loginIdentityEnabled()) {
            $results[] = $hit
                ? $this->limiter()->hit($this->loginIdentityKey($identifier), self::LOGIN_IDENTITY_SCOPE, $this->configuration->loginIdentity())
                : $this->limiter()->status($this->loginIdentityKey($identifier), self::LOGIN_IDENTITY_SCOPE, $this->configuration->loginIdentity());
        }

        return $results;
    }

    /**
     * Vrati status nebo hit pouze pro zapnute MFA buckety
     *
     * @return list<RateLimitResult>
     */
    private function mfaResults(ServerRequestInterface $request, int $userId, bool $hit): array
    {
        $results = [];
        if ($this->configuration->mfaIpEnabled()) {
            $results[] = $hit
                ? $this->limiter()->hit($this->mfaIpKey($request), self::MFA_IP_SCOPE, $this->configuration->mfaIp())
                : $this->limiter()->status($this->mfaIpKey($request), self::MFA_IP_SCOPE, $this->configuration->mfaIp());
        }
        if ($this->configuration->mfaIdentityEnabled()) {
            $results[] = $hit
                ? $this->limiter()->hit($this->mfaUserKey($userId), self::MFA_USER_SCOPE, $this->configuration->mfaIdentity())
                : $this->limiter()->status($this->mfaUserKey($userId), self::MFA_USER_SCOPE, $this->configuration->mfaIdentity());
        }

        return $results;
    }

    /**
     * Vrati limiter dostupny pouze pri zapnutem throttlingu
     */
    private function limiter(): RateLimiterInterface
    {
        if ($this->limiter === null) {
            throw new \LogicException('Admin authentication throttler is disabled.');
        }

        return $this->limiter;
    }

    /**
     * Vytvori opaque IP bucket s bezpecnym fallbackem pro chybejici adresu
     */
    private function loginIpKey(ServerRequestInterface $request): string
    {
        return 'admin-auth:login:ip:' . $this->clientIp($request);
    }

    /**
     * Vytvori opaque identity bucket se stejnou normalizaci jako local provider
     */
    private function loginIdentityKey(string $identifier): string
    {
        return 'admin-auth:login:identity:' . LocalAuthenticationProvider::normalizeEmail($identifier);
    }

    /**
     * Vytvori opaque IP bucket MFA challenge
     */
    private function mfaIpKey(ServerRequestInterface $request): string
    {
        return 'admin-auth:mfa:ip:' . $this->clientIp($request);
    }

    /**
     * Vytvori opaque user bucket MFA challenge
     */
    private function mfaUserKey(int $userId): string
    {
        return 'admin-auth:mfa:user:' . $userId;
    }

    /**
     * Vraci canonical klientskou adresu nebo jeden sdileny fallback bucket
     */
    private function clientIp(ServerRequestInterface $request): string
    {
        return $this->requests->clientIp($request) ?? 'unknown';
    }

    /**
     * Slozi jednu odpoved z nezavislych bucketu policy
     *
     * @param list<RateLimitResult> $results
     */
    private function decision(array $results): RateLimitResult
    {
        $allowed = true;
        $remaining = PHP_INT_MAX;
        $retryAfterSeconds = 0;

        foreach ($results as $result) {
            if (!$result->allowed()) {
                $allowed = false;
                $retryAfterSeconds = max($retryAfterSeconds, $result->retryAfterSeconds());
            }

            $remaining = min($remaining, $result->remaining());
        }

        return new RateLimitResult(
            allowed: $allowed,
            remaining: $remaining === PHP_INT_MAX ? 0 : $remaining,
            retryAfterSeconds: $allowed ? 0 : $retryAfterSeconds,
        );
    }
}
