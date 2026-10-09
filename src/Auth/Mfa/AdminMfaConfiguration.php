<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use InvalidArgumentException;

/**
 * Popisuje hostem zvolenou povinnost lokalniho TOTP prihlaseni
 */
final readonly class AdminMfaConfiguration
{
    /**
     * Overi povoleny rezim a zavislosti aktivni MFA capability
     */
    public function __construct(
        public string $localRequirement = 'disabled',
        public ?string $issuer = null,
        public ?string $encryptionKey = null,
    ) {
        if (!in_array($localRequirement, ['disabled', 'optional', 'all'], true)) {
            throw new InvalidArgumentException('Admin MFA local requirement must be disabled, optional, or all.');
        }
        if ($localRequirement === 'disabled') {
            return;
        }
        if (trim((string) $issuer) === '') {
            throw new InvalidArgumentException('Enabled Admin MFA requires an issuer.');
        }
        $key = base64_decode((string) $encryptionKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new InvalidArgumentException('Enabled Admin MFA requires a base64 encoded 32-byte encryption key.');
        }
    }

    /**
     * Rozhoduje zda je MFA capability aktivni
     */
    public function enabled(): bool
    {
        return $this->localRequirement !== 'disabled';
    }

    /**
     * Vraci binarni klic pouze pro aktivni MFA capability
     */
    public function binaryEncryptionKey(): string
    {
        $key = base64_decode((string) $this->encryptionKey, true);
        if ($key === false) {
            throw new \LogicException('Enabled Admin MFA encryption key is invalid.');
        }

        return $key;
    }
}
