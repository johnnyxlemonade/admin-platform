<?php

declare(strict_types=1);

namespace Lemonade\Admin\Identity;

use InvalidArgumentException;
use Lemonade\Framework\Database\Database;
use RuntimeException;

/**
 * Uklada canonical vazby externich identit na lokalni ucty
 */
final class ExternalIdentityRepository
{
    /**
     * Vytvari repository nad portalovou databazi
     */
    public function __construct(private readonly Database $database) {}

    /**
     * Hleda identity podle canonical issuer a subject
     */
    public function find(string $issuer, string $subject): ?ExternalIdentityLink
    {
        $this->assertIdentity($issuer, $subject);
        $rows = $this->database->select(
            'SELECT user_id, provider_key, issuer, subject FROM system_user_identity WHERE issuer = ? AND subject = ? LIMIT 1',
            [$issuer, $subject],
        );

        return $rows === [] ? null : $this->link($rows[0]);
    }

    /**
     * Vraci externalni identity propojene s uzivatelem
     *
     * @return list<ExternalIdentityLink>
     */
    public function forUser(int $userId): array
    {
        return array_map(fn(array $row): ExternalIdentityLink => $this->link($row), $this->database->select(
            'SELECT user_id, provider_key, issuer, subject FROM system_user_identity WHERE user_id = ? ORDER BY id',
            [$userId],
        ));
    }

    /**
     * Overuje, zda ma uzivatel externi credential
     */
    public function hasForUser(int $userId): bool
    {
        return $this->database->select('SELECT 1 FROM system_user_identity WHERE user_id = ? LIMIT 1', [$userId]) !== [];
    }

    /**
     * Vytvari canonical vazbu identity
     */
    public function create(int $userId, string $providerKey, string $issuer, string $subject): void
    {
        $this->assertIdentity($issuer, $subject);
        if ($userId < 1 || trim($providerKey) === '') {
            throw new InvalidArgumentException('External identity link is invalid.');
        }
        $this->database->statement(
            'INSERT INTO system_user_identity (user_id, provider_key, issuer, subject, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $providerKey, $issuer, $subject, $this->now(), $this->now()],
        );
    }

    /**
     * Spousti atomickou operaci identity
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        return $this->database->transaction(static fn() => $callback());
    }

    /**
     * Vytvari lokalni shadow ucet bez hesla
     */
    public function createShadowUser(string $username, ?string $firstName, ?string $lastName, ?string $email): int
    {
        $this->database->statement(
            'INSERT INTO system_user (username, first_name, last_name, email, password_hash, active, version, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, 1, 1, ?, ?)',
            [$username, $firstName, $lastName, $email, $this->now(), $this->now()],
        );
        $id = $this->database->lastInsertId();
        if (!is_int($id) && !is_string($id)) {
            throw new RuntimeException('Shadow user persistence did not return an identifier.');
        }

        return (int) $id;
    }

    /**
     * Hleda lokalni ucet e-mailu vcetne smazanych
     */
    public function findUserByEmail(string $email): ?int
    {
        $rows = $this->database->select('SELECT id FROM system_user WHERE email = ? LIMIT 1', [$email]);

        return $rows === [] ? null : (int) $rows[0]['id'];
    }

    /**
     * Synchronizuje pouze neprzdne profilove hodnoty
     */
    public function syncProfile(int $userId, ?string $firstName, ?string $lastName, ?string $email): void
    {
        $sets = [];
        $bindings = [];
        foreach (['first_name' => $firstName, 'last_name' => $lastName, 'email' => $email] as $column => $value) {
            if ($value !== null && trim($value) !== '') {
                $sets[] = $column . ' = ?';
                $bindings[] = $value;
            }
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at = ?';
        $bindings[] = $this->now();
        $bindings[] = $userId;
        $this->database->statement('UPDATE system_user SET ' . implode(', ', $sets) . ' WHERE id = ?', $bindings);
    }

    /**
     * Vraci stav linked lokalniho uzivatele
     *
     * @return array{active:int,deleted_at:string|null}|null
     */
    public function userState(int $userId): ?array
    {
        $rows = $this->database->select('SELECT active, deleted_at FROM system_user WHERE id = ? LIMIT 1', [$userId]);

        return $rows === [] ? null : ['active' => (int) $rows[0]['active'], 'deleted_at' => $rows[0]['deleted_at'] === null ? null : (string) $rows[0]['deleted_at']];
    }

    /**
     * Overuje existenci role assignmentu lokalniho uzivatele
     */
    public function hasRoleAssignment(int $userId): bool
    {
        return $this->database->select('SELECT 1 FROM system_user_role WHERE user_id = ? LIMIT 1', [$userId]) !== [];
    }

    /**
     * Hleda aktivni roli podle stabilniho business kodu
     */
    public function activeRoleIdByCode(string $roleCode): ?int
    {
        $rows = $this->database->select('SELECT id FROM system_role WHERE code = ? AND deleted_at IS NULL LIMIT 1', [$roleCode]);

        return $rows === [] ? null : (int) $rows[0]['id'];
    }

    /**
     * Prirazuje roli lokalnimu uzivateli v provisioning transakci
     */
    public function assignRole(int $userId, int $roleId): void
    {
        $this->database->statement('INSERT INTO system_user_role (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
    }

    /**
     * Vytvari typed vazbu z databazoveho radku
     *
     * @param array<string,mixed> $row
     */
    private function link(array $row): ExternalIdentityLink
    {
        return new ExternalIdentityLink((int) $row['user_id'], (string) $row['provider_key'], (string) $row['issuer'], (string) $row['subject']);
    }

    /**
     * Vraci aktualni databazovy cas
     */
    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Overuje neprzdne canonical casti identity
     */
    private function assertIdentity(string $issuer, string $subject): void
    {
        if (trim($issuer) === '' || trim($subject) === '') {
            throw new InvalidArgumentException('External identity issuer and subject are required.');
        }
    }
}
