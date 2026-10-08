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
final class OidcAuthenticationService
{
    public function __construct(
        private readonly OidcProviderConfiguration $configuration,
        private readonly OpenIdConnectClientInterface $client,
        private readonly OidcAuthorizationTransactionStore $transactions,
        private readonly OidcAdmissionPolicy $admission,
        private readonly OidcShadowUserProvisioner $provisioner,
        private readonly AuthenticationService $authentication,
        private readonly AdminRoutingConfiguration $routing,
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
        try {
            $userId = $this->provisioner->localUserId($identity, $this->configuration->defaultRole());
        } catch (OidcProvisioningException $exception) {
            throw new OidcProtocolException($exception->getMessage());
        }
        $this->authentication->loginOidc($userId, $identity->provider(), $login->idToken());

        return $transaction->intendedPath() ?? $this->routing->basePath;
    }
}
