<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Framework\Database\Database;

/**
 * Poskytuje data potrebna pro overeni identity
 */
final class LocalAuthenticationProvider
{
    /**
     * Nastavuje data potrebna pro overeni identity
     */
    public function __construct(private readonly Database $database) {}

    /**
     * Overuje prihlasovaci udaje a vraci vysledek pokusu
     */
    public function authenticate(string $identifier, string $password): AuthenticationAttempt
    {
        $email = self::normalizeEmail($identifier);
        if ($email === '' || $password === '') {
            return AuthenticationAttempt::invalid();
        }

        $rows = $this->database->select(
            'SELECT id, email, password_hash, active FROM system_user WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [$email],
        );
        if ($rows === []) {
            return AuthenticationAttempt::invalid();
        }

        $row = $rows[0];
        $hash = $row['password_hash'] ?? null;
        if ($hash === null || !is_string($hash) || $hash === '' || !password_verify($password, $hash)) {
            return AuthenticationAttempt::invalid();
        }
        if ((int) ($row['active'] ?? 0) !== 1) {
            return AuthenticationAttempt::inactive();
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->database->statement(
                'UPDATE system_user SET password_hash = ?, updated_at = ? WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), (int) $row['id']],
            );
        }

        return AuthenticationAttempt::successful(new AuthenticatedUser((int) $row['id'], (string) $row['email']));
    }

    /**
     * Vraci nebo zpracovava hodnotu normalizeemail pro overeni identity
     */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * Rozhoduje stav hashpassword
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
