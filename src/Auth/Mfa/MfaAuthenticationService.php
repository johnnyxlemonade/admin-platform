<?php

declare(strict_types=1);

namespace Lemonade\Admin\Auth\Mfa;

use Lemonade\Admin\Audit\AuditActor;
use Lemonade\Admin\Audit\AuditLogWriterInterface;
use Lemonade\Admin\Auth\AuthenticatedUser;
use Lemonade\Admin\Event\DomainEvent;
use Lemonade\Framework\Database\Database;

/**
 * Ridi MFA rozhodnuti, finalni kontrolu uzivatele a bezpecne auditni udalosti
 */
final readonly class MfaAuthenticationService
{
    /**
     * Nastavuje MFA policy, credentialy, audit a overeni lokalniho uctu
     */
    public function __construct(
        private AdminMfaConfiguration $configuration,
        private TotpCredentialService $credentials,
        private Database $database,
        private AuditLogWriterInterface $audit,
    ) {}

    /**
     * Rozhodne dalsi krok po uspesnem lokalnim hesle
     */
    public function loginRequirement(AuthenticatedUser $user): string
    {
        if (!$this->configuration->enabled()) {
            return 'login';
        }

        if ($this->credentials->hasActiveCredential($user->id())) {
            return 'challenge';
        }

        return $this->configuration->localRequirement === 'all' ? 'enroll' : 'login';
    }

    /**
     * Znovu nacte ucet pro finalni local login a odmitne zmeneny lifecycle
     */
    public function eligibleUser(int $userId): ?AuthenticatedUser
    {
        $rows = $this->database->select(
            'SELECT id, email FROM system_user WHERE id = ? AND active = 1 AND deleted_at IS NULL AND password_hash IS NOT NULL AND password_hash != ? LIMIT 1',
            [$userId, ''],
        );
        if ($rows === []) {
            return null;
        }

        return new AuthenticatedUser((int) $rows[0]['id'], (string) $rows[0]['email']);
    }

    /**
     * Zapise MFA enrollment bez citlivych hodnot
     */
    public function enrolled(int $userId): void
    {
        $this->event('system.authentication.mfa_enrolled', $userId);
    }

    /**
     * Zapise MFA disable bez citlivych hodnot
     */
    public function disabled(int $userId): void
    {
        $this->event('system.authentication.mfa_disabled', $userId);
    }

    /**
     * Zapise administrativni reset bez citlivych hodnot
     */
    public function reset(int $actorUserId, int $targetUserId): void
    {
        $this->audit->record(
            new DomainEvent('system.authentication.mfa_reset', 'system.authentication', 'user', (string) $targetUserId, []),
            AuditActor::user($actorUserId),
        );
    }

    /**
     * Zapise pouze terminalni selhani login challenge
     */
    public function challengeFailed(int $userId): void
    {
        $this->event('system.authentication.mfa_challenge_failed', $userId);
    }

    /**
     * Zapise self-service MFA udalost se stejnym actorem i cilem
     */
    private function event(string $code, int $userId): void
    {
        $this->audit->record(
            new DomainEvent($code, 'system.authentication', 'user', (string) $userId, []),
            AuditActor::user($userId),
        );
    }
}
