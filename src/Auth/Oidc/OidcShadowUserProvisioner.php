<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use Lemonade\Admin\Auth\LocalAuthenticationProvider;
use Lemonade\Admin\Identity\ExternalIdentityRepository;

/**
 * Propojuje overenou OIDC identitu s lokalnim shadow uctem
 */
final class OidcShadowUserProvisioner
{
    /**
     * Vytvari provisioner canonical OIDC identit
     */
    public function __construct(private readonly ExternalIdentityRepository $identities) {}

    /**
     * Vraci existujici nebo nove provisionovany lokalni ucet
     */
    public function localUserId(VerifiedExternalIdentity $identity): int
    {
        $existing = $this->identities->find($identity->issuer(), $identity->subject());
        if ($existing !== null) {
            $this->assertAvailable($existing->userId());
            $this->syncExistingProfile($existing->userId(), $identity);

            return $existing->userId();
        }

        try {
            return $this->identities->transaction(function () use ($identity): int {
                $linked = $this->identities->find($identity->issuer(), $identity->subject());
                if ($linked !== null) {
                    $this->assertAvailable($linked->userId());

                    return $linked->userId();
                }
                $email = $this->email($identity);
                if ($email !== null && $this->identities->findUserByEmail($email) !== null) {
                    throw new OidcProvisioningException('email_collision');
                }
                $userId = $this->identities->createShadowUser(
                    'oidc:' . hash('sha256', $identity->issuer() . "\0" . $identity->subject()),
                    $identity->firstName(),
                    $identity->lastName(),
                    $email,
                );
                $this->identities->create($userId, $identity->provider(), $identity->issuer(), $identity->subject());

                return $userId;
            });
        } catch (OidcProvisioningException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $linked = $this->identities->find($identity->issuer(), $identity->subject());
            if ($linked !== null) {
                $this->assertAvailable($linked->userId());

                return $linked->userId();
            }
            throw new OidcProvisioningException('provisioning_failed', previous: $exception);
        }
    }

    /**
     * Odmita inactive nebo smazany linked ucet
     */
    private function assertAvailable(int $userId): void
    {
        $state = $this->identities->userState($userId);
        if ($state === null || $state['active'] !== 1 || $state['deleted_at'] !== null) {
            throw new OidcProvisioningException('linked_user_unavailable');
        }
    }

    /**
     * Synchronizuje bezpecne profilove hodnoty linked uctu
     */
    private function syncExistingProfile(int $userId, VerifiedExternalIdentity $identity): void
    {
        $email = $this->email($identity);
        if ($email !== null) {
            $owner = $this->identities->findUserByEmail($email);
            if ($owner !== null && $owner !== $userId) {
                $email = null;
            }
        }
        $this->identities->syncProfile($userId, $identity->firstName(), $identity->lastName(), $email);
    }

    /**
     * Vraci normalizovany overeny e-mail nebo null
     */
    private function email(VerifiedExternalIdentity $identity): ?string
    {
        if ($identity->email() === null) {
            return null;
        }
        $email = LocalAuthenticationProvider::normalizeEmail($identity->email());

        return $email === '' ? null : $email;
    }
}
