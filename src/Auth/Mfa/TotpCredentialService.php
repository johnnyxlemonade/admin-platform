<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Security\Totp\TotpConfiguration;
use Lemonade\Framework\Security\Totp\TotpSecret;
use Lemonade\Framework\Security\Totp\TotpSecretGenerator;
use Lemonade\Framework\Security\Totp\TotpVerifier;
use Lemonade\Framework\Security\Totp\VerificationWindow;

/**
 * Manages storage, activation and one-time consumption of local TOTP credentials
 */
final readonly class TotpCredentialService
{
    /**
     * Creates the credential service with encrypted TOTP persistence
     */
    public function __construct(
        private Database $database,
        private TotpCredentialModel $credentials,
        private MfaSecretCipher $cipher,
        private TotpSecretGenerator $generator,
        private TotpVerifier $verifier,
    ) {}

    /**
     * Creates an encrypted enrollment secret and returns it only for immediate display
     */
    public function beginEnrollment(int $userId): TotpSecret
    {
        return $this->database->transaction(function () use ($userId): TotpSecret {
            $this->cleanupExpired($userId, true);
            $secret = $this->generator->generate();
            $now = date('Y-m-d H:i:s');
            $expires = date('Y-m-d H:i:s', time() + 600);
            $ciphertext = $this->cipher->encrypt($secret->toBase32(), $userId);
            $this->credentials->storePendingEnrollment($userId, $ciphertext, $now, $expires, $now);

            return $secret;
        });
    }

    /**
     * Confirms a pending secret and activates it only after a valid TOTP code
     */
    public function confirmEnrollment(int $userId, string $code): bool
    {
        return $this->database->transaction(function () use ($userId, $code): bool {
            $credential = $this->credentials->findForUpdate($userId);

            if ($credential === null || $this->expired($credential)) {
                $this->cleanupExpired($userId, false);

                return false;
            }

            $secret = TotpSecret::fromBase32($this->cipher->decrypt((string) $credential['pending_secret_ciphertext'], $userId));
            $verification = $this->verifier->verify($secret, $code, new TotpConfiguration(), new VerificationWindow(1, 1));

            if (!$verification->isValid()) {
                return false;
            }

            $step = $verification->matchedTimeStep();
            $this->credentials->activatePendingCredential($userId, $step, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));

            return true;
        });
    }

    /**
     * Determines whether the user has an active credential
     */
    public function hasActiveCredential(int $userId): bool
    {
        $this->cleanupExpired($userId, false);
        $credential = $this->credentials->find($userId);

        return $credential !== null && $credential['secret_ciphertext'] !== null && $credential['enabled_at'] !== null;
    }

    /**
     * Returns the activation timestamp of the active credential when available
     */
    public function enabledAt(int $userId): ?string
    {
        $this->cleanupExpired($userId, false);
        $credential = $this->credentials->find($userId);

        if ($credential === null || $credential['secret_ciphertext'] === null || $credential['enabled_at'] === null) {
            return null;
        }

        return (string) $credential['enabled_at'];
    }

    /**
     * Verifies a code and atomically consumes its matching time step
     */
    public function verifyAndConsume(int $userId, string $code): bool
    {
        return $this->database->transaction(function () use ($userId, $code): bool {
            $credential = $this->credentials->findForUpdate($userId);

            if ($credential === null || $credential['secret_ciphertext'] === null || $credential['enabled_at'] === null) {
                return false;
            }

            $secret = TotpSecret::fromBase32($this->cipher->decrypt((string) $credential['secret_ciphertext'], $userId));
            $verification = $this->verifier->verify($secret, $code, new TotpConfiguration(), new VerificationWindow(1, 1));
            $step = $verification->matchedTimeStep();

            if ($step === null || ($credential['last_consumed_time_step'] !== null && $step <= (int) $credential['last_consumed_time_step'])) {
                return false;
            }

            $this->credentials->consumeTimeStep($userId, $step, date('Y-m-d H:i:s'));

            return true;
        });
    }

    /**
     * Removes active and pending credential state during revoke or reset
     */
    public function revoke(int $userId): void
    {
        $this->credentials->revoke($userId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
    }

    /**
     * Determines whether a pending enrollment has expired
     *
     * @param array<string, mixed> $row
     */
    private function expired(array $row): bool
    {
        return $row['enrollment_expires_at'] !== null && strtotime((string) $row['enrollment_expires_at']) < time();
    }

    /**
     * Clears an expired pending enrollment using the requested lock mode
     */
    private function cleanupExpired(int $userId, bool $locked): void
    {
        $credential = $locked ? $this->credentials->findForUpdate($userId) : $this->credentials->find($userId);

        if ($credential !== null && $this->expired($credential)) {
            $this->credentials->clearExpiredPendingEnrollment($userId, date('Y-m-d H:i:s'));
        }
    }
}
