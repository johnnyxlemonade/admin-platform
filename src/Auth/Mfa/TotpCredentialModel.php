<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use Lemonade\Framework\Database\Database;

/**
 * Uklada a nacita sifrovane TOTP credentialy lokalnich uzivatelu
 */
final readonly class TotpCredentialModel
{
    /**
     * Nastavuje databazovy pristup pro TOTP credentialy
     */
    public function __construct(private Database $database) {}

    /**
     * Nacte credential lokalniho uzivatele bez zamku
     *
     * @return array<string,mixed>|null
     */
    public function find(int $userId): ?array
    {
        $rows = $this->database->select(
            'SELECT * FROM system_user_totp WHERE user_id = ? LIMIT 1',
            [$userId],
        );

        return $rows[0] ?? null;
    }

    /**
     * Nacte credential lokalniho uzivatele pod exkluzivnim zamkem
     *
     * Volajici musi predtim otevrit transakci nad stejnym Database pripojenim.
     *
     * @return array<string,mixed>|null
     */
    public function findForUpdate(int $userId): ?array
    {
        $rows = $this->database->select(
            'SELECT * FROM system_user_totp WHERE user_id = ? LIMIT 1 FOR UPDATE',
            [$userId],
        );

        return $rows[0] ?? null;
    }

    /**
     * Ulozi novy rozpracovany enrollment bez zruseni aktivniho credentialu
     */
    public function storePendingEnrollment(int $userId, string $ciphertext, string $startedAt, string $expiresAt, string $updatedAt): void
    {
        $this->database->statement(
            'INSERT INTO system_user_totp(user_id,pending_secret_ciphertext,enrollment_started_at,enrollment_expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE pending_secret_ciphertext=VALUES(pending_secret_ciphertext), enrollment_started_at=VALUES(enrollment_started_at), enrollment_expires_at=VALUES(enrollment_expires_at), updated_at=VALUES(updated_at)',
            [$userId, $ciphertext, $startedAt, $expiresAt, $startedAt, $updatedAt],
        );
    }

    /**
     * Odstrani pouze rozpracovany enrollment, ktery uz neni platny
     */
    public function clearExpiredPendingEnrollment(int $userId, string $updatedAt): void
    {
        $this->database->statement(
            'UPDATE system_user_totp SET pending_secret_ciphertext=NULL,enrollment_started_at=NULL,enrollment_expires_at=NULL,updated_at=? WHERE user_id=?',
            [$updatedAt, $userId],
        );
    }

    /**
     * Aktivuje overeny pending credential a zaznamena spotrebovany TOTP krok
     */
    public function activatePendingCredential(int $userId, ?int $timeStep, string $enabledAt, string $updatedAt): void
    {
        $this->database->statement(
            'UPDATE system_user_totp SET secret_ciphertext=pending_secret_ciphertext,pending_secret_ciphertext=NULL,enrollment_started_at=NULL,enrollment_expires_at=NULL,enabled_at=?,last_consumed_time_step=?,updated_at=? WHERE user_id=?',
            [$enabledAt, $timeStep, $updatedAt, $userId],
        );
    }

    /**
     * Zaznamena uspesne pouzity TOTP krok pro ochranu proti replayi
     */
    public function consumeTimeStep(int $userId, int $timeStep, string $updatedAt): void
    {
        $this->database->statement(
            'UPDATE system_user_totp SET last_consumed_time_step=?,updated_at=? WHERE user_id=?',
            [$timeStep, $updatedAt, $userId],
        );
    }

    /**
     * Zrusi aktivni i rozpracovany credential a zaznamena cas zruseni
     */
    public function revoke(int $userId, string $revokedAt, string $updatedAt): void
    {
        $this->database->statement(
            'UPDATE system_user_totp SET secret_ciphertext=NULL,pending_secret_ciphertext=NULL,enrollment_started_at=NULL,enrollment_expires_at=NULL,enabled_at=NULL,last_consumed_time_step=NULL,last_revoked_at=?,updated_at=? WHERE user_id=?',
            [$revokedAt, $updatedAt, $userId],
        );
    }
}
