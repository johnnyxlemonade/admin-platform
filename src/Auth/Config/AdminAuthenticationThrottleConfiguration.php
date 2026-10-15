<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Config;

use Lemonade\Framework\RateLimit\RateLimitPolicy;
use LogicException;

/**
 * Poskytuje canonical fixed-window policy pro local login a MFA challenge
 */
final readonly class AdminAuthenticationThrottleConfiguration
{
    private const int LOGIN_IP_ATTEMPTS = 20;
    private const int LOGIN_IDENTITY_ATTEMPTS = 5;
    private const int MFA_IP_ATTEMPTS = 20;
    private const int MFA_USER_ATTEMPTS = 5;
    private const int WINDOW_SECONDS = 900;

    /**
     * Nastavuje canonical Admin policy nebo explicitne vypnuty throttling
     */
    private function __construct(
        private bool $enabled,
        private ?RateLimitPolicy $loginIp = null,
        private ?RateLimitPolicy $loginIdentity = null,
        private ?RateLimitPolicy $mfaIp = null,
        private ?RateLimitPolicy $mfaIdentity = null,
        private bool $loginIpEnabled = true,
        private bool $loginIdentityEnabled = true,
        private bool $mfaIpEnabled = true,
        private bool $mfaIdentityEnabled = true,
    ) {
        if ($this->enabled && ($this->loginIp === null || $this->loginIdentity === null || $this->mfaIp === null || $this->mfaIdentity === null)) {
            throw new LogicException('Enabled Admin authentication throttling requires all bucket policies.');
        }
    }

    public static function defaults(
        bool $loginIpEnabled = true,
        bool $loginIdentityEnabled = true,
        bool $mfaIpEnabled = true,
        bool $mfaIdentityEnabled = true,
    ): self {
        return new self(
            enabled: true,
            loginIp: new RateLimitPolicy(self::LOGIN_IP_ATTEMPTS, self::WINDOW_SECONDS),
            loginIdentity: new RateLimitPolicy(self::LOGIN_IDENTITY_ATTEMPTS, self::WINDOW_SECONDS),
            mfaIp: new RateLimitPolicy(self::MFA_IP_ATTEMPTS, self::WINDOW_SECONDS),
            mfaIdentity: new RateLimitPolicy(self::MFA_USER_ATTEMPTS, self::WINDOW_SECONDS),
            loginIpEnabled: $loginIpEnabled,
            loginIdentityEnabled: $loginIdentityEnabled,
            mfaIpEnabled: $mfaIpEnabled,
            mfaIdentityEnabled: $mfaIdentityEnabled,
        );
    }

    public static function disabled(): self
    {
        return new self(enabled: false);
    }

    /**
     * Urci, zda se ma pro login a MFA pouzit rate limiter
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function hasEnabledBuckets(): bool
    {
        return $this->enabled && ($this->loginIpEnabled || $this->loginIdentityEnabled || $this->mfaIpEnabled || $this->mfaIdentityEnabled);
    }

    public function loginIpEnabled(): bool
    {
        return $this->loginIpEnabled;
    }

    public function loginIdentityEnabled(): bool
    {
        return $this->loginIdentityEnabled;
    }

    public function mfaIpEnabled(): bool
    {
        return $this->mfaIpEnabled;
    }

    public function mfaIdentityEnabled(): bool
    {
        return $this->mfaIdentityEnabled;
    }

    /**
     * Vraci policy pro IP bucket lokalniho loginu
     */
    public function loginIp(): RateLimitPolicy
    {
        return $this->policy($this->loginIp);
    }

    /**
     * Vraci policy pro identity bucket lokalniho loginu
     */
    public function loginIdentity(): RateLimitPolicy
    {
        return $this->policy($this->loginIdentity);
    }

    /**
     * Vraci policy pro IP bucket MFA challenge
     */
    public function mfaIp(): RateLimitPolicy
    {
        return $this->policy($this->mfaIp);
    }

    /**
     * Vraci policy pro user bucket MFA challenge
     */
    public function mfaIdentity(): RateLimitPolicy
    {
        return $this->policy($this->mfaIdentity);
    }

    /**
     * Vrati policy dostupnou pouze pri zapnutem throttlingu
     */
    private function policy(?RateLimitPolicy $policy): RateLimitPolicy
    {
        if ($policy === null) {
            throw new LogicException('Disabled Admin authentication throttling has no bucket policies.');
        }

        return $policy;
    }
}
