<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Audit\Models;

use Lemonade\Framework\Database\Model;

/**
 * Provides the safe audit projection used by the current user's profile
 */
final class PersonalAccountActivityReadModel extends Model
{
    protected string $table = 'system_audit_log';

    /**
     * Event codes exposed by the personal account timeline
     *
     * @var list<string>
     */
    private const array ALLOWED_EVENTS = [
        'system.authentication.login',
        'system.authentication.logout',
        'system.users.created',
        'system.users.updated',
        'system.users.password_changed',
        'system.authentication.mfa_enrolled',
        'system.authentication.mfa_disabled',
    ];

    /**
     * Returns a bounded account timeline without exposing audit payloads
     *
     * @return list<array{
     *     actor_email: string|null,
     *     actor_first_name: string|null,
     *     actor_last_name: string|null,
     *     actor_user_id: int|null,
     *     actor_key: string,
     *     actor_type: string,
     *     created_at: string,
     *     event_code: string
     * }>
     */
    public function recent(int $userId, int $limit = 20): array
    {
        if ($userId < 1 || $limit < 1) {
            return [];
        }

        $rows = $this->query()
            ->from('system_audit_log a')
            ->select([
                'a.event_code',
                'a.actor_type',
                'a.actor_key',
                'a.actor_user_id',
                'a.created_at',
            ])
            ->selectRaw('u.first_name AS actor_first_name, u.last_name AS actor_last_name, u.email AS actor_email')
            ->joinOn('system_user u', 'u.id', '=', 'a.actor_user_id', 'LEFT')
            ->where('a.entity_type', 'user')
            ->where('a.entity_key', (string) $userId)
            ->whereIn('a.event_code', self::ALLOWED_EVENTS)
            ->orderBy('a.created_at', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->limit(min($limit, 50))
            ->getArray();

        return array_map(static fn(array $row): array => [
            'event_code' => (string) $row['event_code'],
            'actor_type' => (string) $row['actor_type'],
            'actor_key' => (string) $row['actor_key'],
            'actor_user_id' => $row['actor_user_id'] === null ? null : (int) $row['actor_user_id'],
            'actor_first_name' => $row['actor_first_name'] === null ? null : (string) $row['actor_first_name'],
            'actor_last_name' => $row['actor_last_name'] === null ? null : (string) $row['actor_last_name'],
            'actor_email' => $row['actor_email'] === null ? null : (string) $row['actor_email'],
            'created_at' => (string) $row['created_at'],
        ], $rows);
    }

    /**
     * Returns the most recent supported event timestamp for account metadata
     */
    public function latestProfileUpdateAt(int $userId): ?string
    {
        return $this->latestEventAt($userId, 'system.users.updated');
    }

    /**
     * Returns the most recent password change timestamp for account metadata
     */
    public function latestPasswordChangeAt(int $userId): ?string
    {
        return $this->latestEventAt($userId, 'system.users.password_changed');
    }

    /**
     * Returns the latest allowlisted event timestamp for one account
     */
    private function latestEventAt(int $userId, string $eventCode): ?string
    {
        if ($userId < 1 || !in_array($eventCode, self::ALLOWED_EVENTS, true)) {
            return null;
        }

        $row = $this->query()
            ->from('system_audit_log a')
            ->select('a.created_at')
            ->where('a.entity_type', 'user')
            ->where('a.entity_key', (string) $userId)
            ->where('a.event_code', $eventCode)
            ->orderBy('a.created_at', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->first();

        return $row === null ? null : (string) $row['created_at'];
    }
}
