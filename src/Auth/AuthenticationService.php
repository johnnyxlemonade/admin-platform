<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\Config\AdminAuthConfigDefinition;
use Lemonade\Admin\Editor\Lock\EditorLockOwnerReleaserInterface;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Framework\Clock\ClockInterface;
use Lemonade\Framework\Database\Database;
use Lemonade\Framework\Security\Csrf\CsrfTokenManager;
use Lemonade\Framework\Session\Contract\SessionInterface;

/**
 * Ridi prihlaseni a ukonceni relace administrace
 */
final readonly class AuthenticationService
{
    private const string SESSION_USER_ID = 'admin.auth.user_id';
    private const string SESSION_AUTHENTICATED_AT = 'admin.auth.authenticated_at';
    private const string SESSION_LAST_ACTIVITY_AT = 'admin.auth.last_activity_at';
    private const string SESSION_OIDC_LOGOUT_CONTEXT = 'admin.auth.external_principal';
    private const string SESSION_OIDC_LOGOUT_ID_TOKEN = 'admin.auth.oidc.logout_id_token';
    private const string SESSION_OIDC_IDENTITY = 'admin.auth.oidc.identity';
    private const int IDLE_TIMEOUT_SECONDS = 1200;

    /**
     * Nastavi zavislosti pro prihlaseni, odhlaseni a audit
     */
    public function __construct(
        private LocalAuthenticationProvider $localAuthentication,
        private SessionInterface $session,
        private Database $database,
        private CsrfTokenManager $csrf,
        private AuditLogWriterInterface $audit,
        private ?EditorLockOwnerReleaserInterface $locks = null,
        private ?ClockInterface $clock = null,
    ) {}

    /**
     * Overi lokalni prihlasovaci udaje
     */
    public function authenticateLocal(string $identifier, string $password): AuthenticationAttempt
    {
        return $this->localAuthentication->authenticate($identifier, $password);
    }

    /**
     * Obnovi anonymni relaci po prvnim faktoru pred ulozenim MFA challenge
     */
    public function beginMfaChallenge(): void
    {
        $this->session->start();
        $this->session->regenerate(true);
        $this->csrf->regenerate();
    }

    /**
     * Zalozi lokalni relaci uzivatele a zapise audit prihlaseni
     */
    public function login(AuthenticatedUser $user, bool $mfa = false): void
    {
        $this->session->start();
        $this->session->regenerate(true);
        $this->csrf->regenerate();
        $this->session->set(self::SESSION_USER_ID, $user->id());
        $this->establishSessionLifetime();
        $this->session->remove(self::SESSION_OIDC_LOGOUT_CONTEXT);
        $this->session->remove(self::SESSION_OIDC_LOGOUT_ID_TOKEN);
        $this->database->statement(
            'UPDATE system_user SET last_login_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $user->id()],
        );
        $this->audit->record(
            new DomainEvent('system.authentication.login', 'system.authentication', 'user', (string) $user->id(), $mfa ? ['method' => 'local', 'mfa' => 'totp'] : ['method' => 'local']),
            AuditActor::user($user->id()),
        );
    }

    /**
     * Zalozi OIDC relaci uzivatele a zapise audit prihlaseni
     */
    public function loginOidc(int $userId, string $providerKey, string $idToken, string $issuer = '', string $subject = ''): void
    {
        $this->session->start();
        $this->session->regenerate(true);
        $this->csrf->regenerate();
        $this->session->set(self::SESSION_USER_ID, $userId);
        $this->establishSessionLifetime();
        $this->session->set(self::SESSION_OIDC_LOGOUT_CONTEXT, [
            'kind' => 'external_oidc', 'providerKey' => $providerKey,
        ]);
        $this->session->set(self::SESSION_OIDC_LOGOUT_ID_TOKEN, $idToken);
        if ($issuer !== '' && $subject !== '') {
            $this->session->set(self::SESSION_OIDC_IDENTITY, ['providerKey' => $providerKey, 'issuer' => $issuer, 'subject' => $subject]);
        }
        $this->database->statement(
            'UPDATE system_user SET last_login_at = ?, updated_at = ? WHERE id = ? AND deleted_at IS NULL',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $userId],
        );
        $this->audit->record(
            new DomainEvent('system.authentication.login', 'system.authentication', 'user', (string) $userId, [
                'method' => 'oidc',
                'provider' => $providerKey,
            ]),
            AuditActor::user($userId),
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
                AuditActor::user($userId),
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
     * Overuje canonical idle a absolutni lifetime aktualni Admin relace
     */
    public function hasActiveSession(): bool
    {
        return $this->sessionState() === AdminSessionState::ACTIVE;
    }

    /**
     * Vraci aktualni odvozeny stav administracni relace
     */
    public function sessionState(): AdminSessionState
    {
        if ($this->sessionUserId() === null) {
            return AdminSessionState::UNAUTHENTICATED;
        }

        $authenticatedAt = $this->session->get(self::SESSION_AUTHENTICATED_AT);
        $lastActivityAt = $this->session->get(self::SESSION_LAST_ACTIVITY_AT);
        if (!is_int($authenticatedAt) || !is_int($lastActivityAt)) {
            $this->clearAuthenticatedSession();

            return AdminSessionState::UNAUTHENTICATED;
        }

        $now = $this->nowTimestamp();
        if ($now - $authenticatedAt >= AdminAuthConfigDefinition::MAXIMUM_SESSION_LIFETIME_SECONDS) {
            $this->clearAuthenticatedSession();

            return AdminSessionState::EXPIRED;
        }

        if ($now - $lastActivityAt >= self::IDLE_TIMEOUT_SECONDS) {
            return AdminSessionState::LOCKED;
        }

        return AdminSessionState::ACTIVE;
    }

    /**
     * Posouva idle aktivitu pouze pro explicitni heartbeat platne relace
     */
    public function heartbeat(): bool
    {
        if ($this->sessionState() !== AdminSessionState::ACTIVE) {
            return false;
        }

        $this->session->set(self::SESSION_LAST_ACTIVITY_AT, $this->nowTimestamp());

        return true;
    }

    /**
     * Obnovi pouze idle aktivitu uspesne znovu overene uzamcene relace
     */
    public function unlock(): bool
    {
        if ($this->sessionState() !== AdminSessionState::LOCKED) {
            return false;
        }

        $this->session->set(self::SESSION_LAST_ACTIVITY_AT, $this->nowTimestamp());

        return true;
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

    /**
     * Vraci identitu, kterou musi OIDC reautentizace potvrdit
     *
     * @return array{providerKey:string,issuer:string,subject:string}|null
     */
    public function oidcIdentity(): ?array
    {
        $value = $this->session->get(self::SESSION_OIDC_IDENTITY);
        if (!is_array($value) || !is_string($value['providerKey'] ?? null) || !is_string($value['issuer'] ?? null) || !is_string($value['subject'] ?? null)) {
            return null;
        }

        return ['providerKey' => $value['providerKey'], 'issuer' => $value['issuer'], 'subject' => $value['subject']];
    }

    /**
     * Aktualizuje token pouzivany pouze pro nasledne OIDC odhlaseni
     */
    public function replaceOidcLogoutIdToken(string $idToken): void
    {
        $this->session->set(self::SESSION_OIDC_LOGOUT_ID_TOKEN, $idToken);
    }

    /**
     * Nastavuje shodny login cas pro absolutni i idle lifetime nove relace
     */
    private function establishSessionLifetime(): void
    {
        $timestamp = $this->nowTimestamp();
        $this->session->set(self::SESSION_AUTHENTICATED_AT, $timestamp);
        $this->session->set(self::SESSION_LAST_ACTIVITY_AT, $timestamp);
    }

    /**
     * Odstranuje pouze identitu a casove udaje ukoncene Admin relace
     */
    private function clearAuthenticatedSession(): void
    {
        $this->session->remove(self::SESSION_USER_ID);
        $this->session->remove(self::SESSION_AUTHENTICATED_AT);
        $this->session->remove(self::SESSION_LAST_ACTIVITY_AT);
        $this->session->remove(self::SESSION_OIDC_LOGOUT_CONTEXT);
        $this->session->remove(self::SESSION_OIDC_LOGOUT_ID_TOKEN);
        $this->session->remove(self::SESSION_OIDC_IDENTITY);
    }

    /**
     * Vraci aktualni Unix timestamp z frameworkovych hodin nebo systemu
     */
    private function nowTimestamp(): int
    {
        return $this->clock?->now()->getTimestamp() ?? time();
    }
}
