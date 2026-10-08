<?php

declare(strict_types=1);

namespace Lemonade\Admin\Tests\Unit\Auth;

use Lemonade\Admin\Auth\Oidc\OidcProvisioningException;
use Lemonade\Admin\Auth\Oidc\OidcShadowUserProvisioner;
use Lemonade\Admin\Auth\Oidc\VerifiedExternalIdentity;
use Lemonade\Admin\Identity\ExternalIdentityRepository;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use PHPUnit\Framework\TestCase;

/**
 * Overuje provisioning volitelne vychozi OIDC role
 */
final class OidcShadowUserProvisionerTest extends TestCase
{
    /**
     * Overuje zachovani existujici role linked OIDC uzivatele
     */
    public function testExistingUserRoleRemainsUnchanged(): void
    {
        [$provisioner, $state] = $this->fixture(
            identities: [$this->identityKey() => 7],
            roles: ['editor' => 12],
            assignments: [7 => 91],
        );

        self::assertSame(7, $provisioner->localUserId($this->identity(), 'editor'));
        self::assertSame(91, $state->assignments[7]);
        self::assertSame(0, $state->roleWrites);
    }

    /**
     * Overuje prirazeni configured role linked uzivateli bez assignmentu
     */
    public function testExistingUserWithoutRoleReceivesDefaultRole(): void
    {
        [$provisioner, $state] = $this->fixture(
            identities: [$this->identityKey() => 7],
            roles: ['editor' => 12],
        );

        self::assertSame(7, $provisioner->localUserId($this->identity(), 'editor'));
        self::assertSame(12, $state->assignments[7]);
        self::assertSame(1, $state->roleWrites);
    }

    /**
     * Overuje prirazeni configured role novemu shadow uzivateli
     */
    public function testNewShadowUserReceivesDefaultRole(): void
    {
        [$provisioner, $state] = $this->fixture(roles: ['editor' => 12]);

        self::assertSame(101, $provisioner->localUserId($this->identity(), 'editor'));
        self::assertSame(12, $state->assignments[101]);
        self::assertSame(1, $state->roleWrites);
    }

    /**
     * Overuje explicitni selhani pro chybejici configured roli
     */
    public function testMissingConfiguredRoleFailsExplicitly(): void
    {
        [$provisioner] = $this->fixture(identities: [$this->identityKey() => 7]);

        $this->expectException(OidcProvisioningException::class);
        $this->expectExceptionMessage('default_role_not_found');

        $provisioner->localUserId($this->identity(), 'editor');
    }

    /**
     * Overuje absenci automaticke role bez configured hodnoty
     */
    public function testUnsetDefaultRoleDoesNotAssignRole(): void
    {
        [$provisioner, $state] = $this->fixture(identities: [$this->identityKey() => 7]);

        self::assertSame(7, $provisioner->localUserId($this->identity()));
        self::assertArrayNotHasKey(7, $state->assignments);
        self::assertSame(0, $state->roleWrites);
    }

    /**
     * Overuje idempotentni assignment pri opakovanem OIDC loginu
     */
    public function testRepeatedLoginDoesNotDuplicateOrChangeRole(): void
    {
        [$provisioner, $state] = $this->fixture(
            identities: [$this->identityKey() => 7],
            roles: ['editor' => 12],
        );

        $provisioner->localUserId($this->identity(), 'editor');
        $provisioner->localUserId($this->identity(), 'editor');

        self::assertSame(12, $state->assignments[7]);
        self::assertSame(1, $state->roleWrites);
    }

    /**
     * Vytvori provisioner s pametovym DB contractem
     *
     * @param array<string, int> $identities
     * @param array<string, int> $roles
     * @param array<int, int> $assignments
     * @return array{OidcShadowUserProvisioner, \stdClass}
     */
    private function fixture(array $identities = [], array $roles = [], array $assignments = []): array
    {
        $state = (object) [
            'identities' => $identities,
            'roles' => $roles,
            'assignments' => $assignments,
            'users' => array_fill_keys(array_values($identities), ['active' => 1, 'deleted_at' => null]),
            'lastInsertId' => 100,
            'roleWrites' => 0,
        ];
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('select')->willReturnCallback(static function (string $sql, array $bindings) use ($state): array {
            if (str_contains($sql, 'FROM system_user_identity')) {
                $userId = $state->identities[$bindings[0] . "\0" . $bindings[1]] ?? null;

                return $userId === null ? [] : [[
                    'user_id' => $userId,
                    'provider_key' => 'provider-a',
                    'issuer' => $bindings[0],
                    'subject' => $bindings[1],
                ]];
            }
            if (str_contains($sql, 'FROM system_user WHERE id')) {
                $user = $state->users[$bindings[0]] ?? null;

                return $user === null ? [] : [$user];
            }
            if (str_contains($sql, 'FROM system_user_role')) {
                return isset($state->assignments[$bindings[0]]) ? [['role_id' => $state->assignments[$bindings[0]]]] : [];
            }
            if (str_contains($sql, 'FROM system_role')) {
                $roleId = $state->roles[$bindings[0]] ?? null;

                return $roleId === null ? [] : [['id' => $roleId]];
            }
            if (str_contains($sql, 'FROM system_user WHERE email')) {
                return [];
            }

            return [];
        });
        $connection->method('statement')->willReturnCallback(static function (string $sql, array $bindings) use ($state): int {
            if (str_starts_with($sql, 'INSERT INTO system_user (')) {
                ++$state->lastInsertId;
                $state->users[$state->lastInsertId] = ['active' => 1, 'deleted_at' => null];
            }
            if (str_starts_with($sql, 'INSERT INTO system_user_identity')) {
                $state->identities[$bindings[2] . "\0" . $bindings[3]] = $bindings[0];
            }
            if (str_starts_with($sql, 'INSERT INTO system_user_role')) {
                $state->assignments[$bindings[0]] = $bindings[1];
                ++$state->roleWrites;
            }

            return 1;
        });
        $connection->method('transaction')->willReturnCallback(static fn(callable $callback): mixed => $callback());
        $connection->method('inTransaction')->willReturn(false);
        $connection->method('lastInsertId')->willReturnCallback(static fn(): int => $state->lastInsertId);

        return [
            new OidcShadowUserProvisioner(new ExternalIdentityRepository(new Database($connection, $this->createMock(DatabaseDriverInterface::class)))),
            $state,
        ];
    }

    /**
     * Vraci overenou externi identitu pro provisioning test
     */
    private function identity(): VerifiedExternalIdentity
    {
        return new VerifiedExternalIdentity('provider-a', 'https://issuer.example.test', 'subject-123', null);
    }

    /**
     * Vraci canonical klic testovaci OIDC identity
     */
    private function identityKey(): string
    {
        return 'https://issuer.example.test' . "\0" . 'subject-123';
    }
}
