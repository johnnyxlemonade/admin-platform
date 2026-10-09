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
final class TotpCredentialService
{
    public function __construct(
        private readonly Database $database,
        private readonly MfaSecretCipher $cipher,
        private readonly TotpSecretGenerator $generator,
        private readonly TotpVerifier $verifier,
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
            $this->database->statement('INSERT INTO system_user_totp(user_id,pending_secret_ciphertext,enrollment_started_at,enrollment_expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE pending_secret_ciphertext=VALUES(pending_secret_ciphertext), enrollment_started_at=VALUES(enrollment_started_at), enrollment_expires_at=VALUES(enrollment_expires_at), updated_at=VALUES(updated_at)', [$userId, $ciphertext, $now, $expires, $now, $now]);
            return $secret;
        });
    }

    /**
     * Potvrdi pending secret a aktivuje jej az po platnem TOTP kodu
     */
    public function confirmEnrollment(int $userId, string $code): bool
    {
        return $this->database->transaction(function () use ($userId, $code): bool {
            $rows = $this->credential($userId, true);
            if ($rows === [] || $this->expired($rows[0])) {
                $this->cleanupExpired($userId, false);

                return false;
            }
            $secret = TotpSecret::fromBase32($this->cipher->decrypt((string) $rows[0]['pending_secret_ciphertext'], $userId));
            $verification = $this->verifier->verify($secret, $code, new TotpConfiguration(), new VerificationWindow(1, 1));
            if (!$verification->isValid()) {
                return false;
            }
            $step = $verification->matchedTimeStep();
            $this->database->statement('UPDATE system_user_totp SET secret_ciphertext=pending_secret_ciphertext,pending_secret_ciphertext=NULL,enrollment_started_at=NULL,enrollment_expires_at=NULL,enabled_at=?,last_consumed_time_step=?,updated_at=? WHERE user_id=?', [date('Y-m-d H:i:s'), $step, date('Y-m-d H:i:s'), $userId]);
            return true;
        });
    }

    /**
     * Overi zda existuje aktivni credential
     */
    public function hasActiveCredential(int $userId): bool
    {
        $this->cleanupExpired($userId, false);
        $rows = $this->credential($userId);
        return $rows !== [] && $rows[0]['secret_ciphertext'] !== null && $rows[0]['enabled_at'] !== null;
    }

    /**
     * Overi kod a atomicky spotrebuje jeho matched time-step
     */
    public function verifyAndConsume(int $userId, string $code): bool
    {
        return $this->database->transaction(function () use ($userId, $code): bool {
            $rows = $this->credential($userId, true);
            if ($rows === [] || $rows[0]['secret_ciphertext'] === null || $rows[0]['enabled_at'] === null) {
                return false;
            }
            $secret = TotpSecret::fromBase32($this->cipher->decrypt((string) $rows[0]['secret_ciphertext'], $userId));
            $verification = $this->verifier->verify($secret, $code, new TotpConfiguration(), new VerificationWindow(1, 1));
            $step = $verification->matchedTimeStep();
            if ($step === null || ($rows[0]['last_consumed_time_step'] !== null && $step <= (int) $rows[0]['last_consumed_time_step'])) {
                return false;
            }
            $this->database->statement('UPDATE system_user_totp SET last_consumed_time_step=?,updated_at=? WHERE user_id=?', [$step, date('Y-m-d H:i:s'), $userId]);
            return true;
        });
    }

    /**
     * Odstrani aktivni i rozpracovany credential pri revoke nebo resetu
     */
    public function revoke(int $userId): void
    {
        $this->database->statement('UPDATE system_user_totp SET secret_ciphertext=NULL,pending_secret_ciphertext=NULL,enrollment_started_at=NULL,enrollment_expires_at=NULL,enabled_at=NULL,last_consumed_time_step=NULL,last_revoked_at=?,updated_at=? WHERE user_id=?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $userId]);
    }

    /**
     * Nacte credential a podle potreby jej zamkne pro probihajici transakci
     *
     * @return list<array<string,mixed>>
     */
    private function credential(int $userId, bool $lock = false): array
    {
        return $this->database->select('SELECT * FROM system_user_totp WHERE user_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), [$userId]);
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
        $rows = $this->credential($userId, $locked);
        if ($rows !== [] && $this->expired($rows[0])) {
            $this->database->statement('UPDATE system_user_totp SET pending_secret_ciphertext=NULL,enrollment_started_at=NULL,enrollment_expires_at=NULL,updated_at=? WHERE user_id=?', [date('Y-m-d H:i:s'), $userId]);
        }
    }
}
