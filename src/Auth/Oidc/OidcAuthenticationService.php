<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Oidc;

use Lemonade\Admin\Auth\AuthenticationService;
use Lemonade\Admin\Auth\InternalAdminDestination;
use Lemonade\Admin\Auth\Oidc\Contract\OpenIdConnectClientInterface;
use Lemonade\Admin\Routing\AdminRoutingConfiguration;

/**
 * Ridi prihlaseni pres OIDC poskytovatele
 */
final readonly class OidcAuthenticationService
{
    public const string SESSION_UNLOCK_CALLBACK = '__session_unlock_callback__';

    public function __construct(
        private OidcProviderConfiguration $configuration,
        private OpenIdConnectClientInterface $client,
        private OidcAuthorizationTransactionStore $transactions,
        private OidcAdmissionPolicy $admission,
        private OidcShadowUserProvisioner $provisioner,
        private AuthenticationService $authentication,
        private AdminRoutingConfiguration $routing,
    ) {}

    public function begin(?string $intendedPath): string
    {
        if (!$this->configuration->isEnabled()) {
            throw new OidcProtocolException('provider_disabled');
        }
        $transaction = $this->transactions->begin($this->configuration->provider(), InternalAdminDestination::isSafe($intendedPath, $this->routing) ? $intendedPath : null);
        $metadata = $this->client->discover($this->configuration);

        return $this->client->createAuthorizationRequest($this->configuration, $metadata, $transaction->state(), $transaction->nonce(), $transaction->codeVerifier());
    }

    /**
     * Zahaji popup reautentizaci stale uzamcene OIDC relace
     */
    public function beginSessionUnlock(): string
    {
        $identity = $this->authentication->oidcIdentity();
        $context = $this->authentication->oidcLogoutContext();
        $userId = $this->authentication->sessionUserId();
        if (!$this->configuration->isEnabled() || $this->authentication->sessionState()->value !== 'locked' || $identity === null || $context === null || $userId === null || $context['providerKey'] !== $this->configuration->provider() || $identity['providerKey'] !== $this->configuration->provider()) {
            throw new OidcProtocolException('session_unlock_unavailable');
        }
        $transaction = $this->transactions->beginSessionUnlock($this->configuration->provider(), $userId, $identity['issuer'], $identity['subject']);
        $metadata = $this->client->discover($this->configuration);

        return $this->client->createAuthorizationRequest($this->configuration, $metadata, $transaction->state(), $transaction->nonce(), $transaction->codeVerifier(), true);
    }

    public function complete(OidcAuthorizationCallback $callback): string
    {
        if (!$this->configuration->isEnabled()) {
            throw new OidcProtocolException('provider_disabled');
        }

        $state = $callback->state();
        if ($state === null) {
            throw new OidcProtocolException('authorization_state_invalid');
        }
        $transaction = $this->transactions->consume($this->configuration->provider(), $state);
        $metadata = $this->client->discover($this->configuration);
        $login = $this->client->exchangeAndVerify($this->configuration, $metadata, $callback, $transaction->nonce(), $transaction->codeVerifier());
        $identity = $login->identity();
        if (!$this->admission->allows($this->configuration, $identity)) {
            throw new OidcProtocolException('group_admission_denied');
        }
        $unlockIdentity = $transaction->unlockIdentity();
        if ($unlockIdentity !== null) {
            try {
                $userId = $this->provisioner->linkedLocalUserId($identity);
            } catch (OidcProvisioningException $exception) {
                throw new OidcProtocolException($exception->getMessage());
            }
            if ($this->authentication->sessionState()->value !== 'locked'
                || $this->authentication->sessionUserId() !== $unlockIdentity['userId']
                || $identity->provider() !== $transaction->providerKey()
                || $identity->issuer() !== $unlockIdentity['issuer']
                || $identity->subject() !== $unlockIdentity['subject']
                || $userId !== $unlockIdentity['userId']
                || !$this->authentication->unlock()) {
                throw new OidcProtocolException('session_unlock_identity_mismatch');
            }
            $this->authentication->replaceOidcLogoutIdToken($login->idToken());

            return self::SESSION_UNLOCK_CALLBACK;
        }

        try {
            $userId = $this->provisioner->localUserId($identity, $this->configuration->defaultRole());
        } catch (OidcProvisioningException $exception) {
            throw new OidcProtocolException($exception->getMessage());
        }

        $this->authentication->loginOidc($userId, $identity->provider(), $login->idToken(), $identity->issuer(), $identity->subject());

        return $transaction->intendedPath() ?? $this->routing->basePath;
    }
}
