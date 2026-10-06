<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Audit\AuditOperation;
use Lemonade\Admin\Editor\Lock\EditorLockOwnerReleaserInterface;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Ridi prihlaseni a ukonceni relace administrace
 */
final class AuthenticationService
{
    private const SESSION_USER_ID = 'admin.auth.user_id';
    private const SESSION_OIDC_LOGOUT_CONTEXT = 'admin.auth.external_principal';
    private const SESSION_OIDC_LOGOUT_ID_TOKEN = 'admin.auth.oidc.logout_id_token';

    /**
     * Nastavi zavislosti pro prihlaseni, odhlaseni a audit
     */
    public function __construct(
        private readonly LocalAuthenticationProvider $localAuthentication,
        private readonly SessionInterface $session,
        private readonly Database $database,
        private readonly CsrfTokenManager $csrf,
        private readonly AuditLogWriterInterface $audit,
        private readonly ?EditorLockOwnerReleaserInterface $locks = null,
    ) {}

    /**
     * Overi lokalni prihlasovaci udaje
     */
    public function authenticateLocal(string $identifier, string $password): AuthenticationAttempt
    {
        return $this->localAuthentication->authenticate($identifier, $password);
    }

    /**
     * Zalozi lokalni relaci uzivatele a zapise audit prihlaseni
     */
    public function login(AuthenticatedUser $user): void
    {
        $this->session->start();
        $this->session->regenerate(true);
        $this->csrf->regenerate();
        $this->session->set(self::SESSION_USER_ID, $user->id());
        $this->session->remove(self::SESSION_OIDC_LOGOUT_CONTEXT);
        $this->session->remove(self::SESSION_OIDC_LOGOUT_ID_TOKEN);
        $this->database->statement(
            'UPDATE system_user SET last_login_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $user->id()],
        );
        $this->audit->record(
            new DomainEvent('system.authentication.login', 'system.authentication', 'user', (string) $user->id(), ['method' => 'local']),
            new AuditOperation('system.authentication', 'auth.login', AuditActor::user($user->id())),
        );
    }

    /**
     * Zalozi OIDC relaci uzivatele a zapise audit prihlaseni
     */
    public function loginOidc(int $userId, string $providerKey, string $idToken): void
    {
        $this->session->start();
        $this->session->regenerate(true);
        $this->csrf->regenerate();
        $this->session->set(self::SESSION_USER_ID, $userId);
        $this->session->set(self::SESSION_OIDC_LOGOUT_CONTEXT, [
            'kind' => 'external_oidc', 'providerKey' => $providerKey,
        ]);
        $this->session->set(self::SESSION_OIDC_LOGOUT_ID_TOKEN, $idToken);
        $this->database->statement(
            'UPDATE system_user SET last_login_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $userId],
        );
        $this->audit->record(
            new DomainEvent('system.authentication.login', 'system.authentication', 'user', (string) $userId, [
                'method' => 'oidc',
                'provider' => $providerKey,
            ]),
            new AuditOperation('system.authentication', 'auth.login', AuditActor::user($userId)),
        );
    }

    /**
     * Ukonci relaci a zapise audit odhlaseni prihlaseneho uzivatele
     */
    public function logout(): void
    {
        $this->session->start();
        $userId = $this->sessionUserId();
        $oidcLogoutContext = $this->oidcLogoutContext();
        if ($userId !== null) {
            $this->locks?->releaseOwnedByUser($userId);
            $payload = $oidcLogoutContext === null
                ? ['method' => 'local']
                : ['method' => 'oidc', 'provider' => $oidcLogoutContext['providerKey']];
            $this->audit->record(
                new DomainEvent('system.authentication.logout', 'system.authentication', 'user', (string) $userId, $payload),
                new AuditOperation('system.authentication', 'auth.logout', AuditActor::user($userId)),
            );
        }
        $this->session->remove(self::SESSION_USER_ID);
        $this->session->clear();
        $this->session->regenerate(true);
    }

    /**
     * Vrati identifikator uzivatele ulozeny v relaci
     */
    public function sessionUserId(): ?int
    {
        $value = $this->session->get(self::SESSION_USER_ID);

        return is_int($value) && $value > 0 ? $value : null;
    }

    /**
     * Vrati OIDC souvislosti potrebne pro odhlaseni pokud jsou ulozene v relaci
     *
     * @return array{kind:string,providerKey:string}|null
     */
    public function oidcLogoutContext(): ?array
    {
        $value = $this->session->get(self::SESSION_OIDC_LOGOUT_CONTEXT);
        if (!is_array($value)
            || ($value['kind'] ?? null) !== 'external_oidc'
            || !is_string($value['providerKey'] ?? null)) {
            return null;
        }

        return [
            'kind' => $value['kind'],
            'providerKey' => $value['providerKey'],
        ];
    }

    /**
     * Vrati ID token potrebny pro OIDC odhlaseni pokud je ulozeny v relaci
     */
    public function oidcLogoutIdToken(): ?string
    {
        $value = $this->session->get(self::SESSION_OIDC_LOGOUT_ID_TOKEN);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
