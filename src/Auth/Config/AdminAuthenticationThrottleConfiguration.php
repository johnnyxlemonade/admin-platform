<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Config;

use Lemonade\Framework\RateLimit\RateLimitPolicy;
use LogicException;

/**
 * Drzi volitelne fixed-window policy pro anonymni local login a MFA challenge
 */
final readonly class AdminAuthenticationThrottleConfiguration
{
    /**
     * Nastavuje zapnuti throttlingu a jeho policy pro IP a identity buckety
     */
    public function __construct(
        private bool $enabled,
        private ?RateLimitPolicy $loginIp = null,
        private ?RateLimitPolicy $loginIdentity = null,
        private ?RateLimitPolicy $mfaIp = null,
        private ?RateLimitPolicy $mfaIdentity = null,
    ) {
        if ($this->enabled && ($this->loginIp === null || $this->loginIdentity === null || $this->mfaIp === null || $this->mfaIdentity === null)) {
            throw new LogicException('Enabled Admin authentication throttling requires all bucket policies.');
        }
    }

    /**
     * Urci, zda se ma pro login a MFA pouzit rate limiter
     */
    public function enabled(): bool
    {
        return $this->enabled;
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
