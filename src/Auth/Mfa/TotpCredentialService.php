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
 * Ridi ulozeni, aktivaci a jednorazove pouziti lokalniho TOTP credentialu
 */
final readonly class TotpCredentialService
{
    public function __construct(
        private Database $database,
        private TotpCredentialModel $credentials,
        private MfaSecretCipher $cipher,
        private TotpSecretGenerator $generator,
        private TotpVerifier $verifier,
    ) {}

    /**
     * Zalozi sifrovany enrollment secret a vrati jej pouze pro okamzite zobrazeni
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
     * Potvrdi pending secret a aktivuje jej az po platnem TOTP kodu
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
     * Overi zda existuje aktivni credential
     */
    public function hasActiveCredential(int $userId): bool
    {
        $this->cleanupExpired($userId, false);
        $credential = $this->credentials->find($userId);

        return $credential !== null && $credential['secret_ciphertext'] !== null && $credential['enabled_at'] !== null;
    }

    /**
     * Overi kod a atomicky spotrebuje jeho matched time-step
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
     * Odstrani aktivni i rozpracovany credential pri revoke nebo resetu
     */
    public function revoke(int $userId): void
    {
        $this->credentials->revoke($userId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'));
    }

    /**
     * Rozhoduje zda rozpracovany enrollment uz vyprsel
     *
     * @param array<string,mixed> $row
     */
    private function expired(array $row): bool
    {
        return $row['enrollment_expires_at'] !== null && strtotime((string) $row['enrollment_expires_at']) < time();
    }

    private function cleanupExpired(int $userId, bool $locked): void
    {
        $credential = $locked ? $this->credentials->findForUpdate($userId) : $this->credentials->find($userId);
        if ($credential !== null && $this->expired($credential)) {
            $this->credentials->clearExpiredPendingEnrollment($userId, date('Y-m-d H:i:s'));
        }
    }
}
