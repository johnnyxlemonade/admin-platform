<?php

declare(strict_types=1);

namespace Lemonade\Admin\Event;

use Lemonade\Admin\Audit\AuditActor;

/**
 * Nese udalost z domeny aplikace
 */
final class DomainEvent
{
    /** @var array<string, mixed> */
    private readonly array $payload;

    private ?int $actorUserId = null;

    private ?AuditActor $auditActor = null;

    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly string $code,
        private readonly string $moduleCode,
        private readonly string $entityType,
        private readonly string $entityKey,
        array $payload = [],
    ) {
        $this->payload = self::safePayload($payload);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function moduleCode(): string
    {
        return $this->moduleCode;
    }

    public function entityKey(): string
    {
        return $this->entityKey;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    /** Actor is attached only by TransactionalEventProcessor after a committed operation. */
    public function withActorUserId(?int $actorUserId): self
    {
        $event = new self($this->code, $this->moduleCode, $this->entityType, $this->entityKey, $this->payload);
        $event->actorUserId = $actorUserId;

        return $event;
    }

    /** Actor is attached only by TransactionalEventProcessor after a committed operation. */
    public function withAuditActor(AuditActor $actor): self
    {
        $event = new self($this->code, $this->moduleCode, $this->entityType, $this->entityKey, $this->payload);
        $event->auditActor = $actor;
        $event->actorUserId = $actor->userId();

        return $event;
    }

    public function actorUserId(): ?int
    {
        return $this->actorUserId;
    }

    public function auditActor(): ?AuditActor
    {
        return $this->auditActor;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Events may be audited or materialized as notifications. Security transport and credential
     * values never belong in either secondary record.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function safePayload(array $payload): array
    {
        $safe = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = self::safePayload($value);

                continue;
            }
            $safe[$key] = $value;
        }

        return $safe;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return $normalized === 'token'
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'csrf')
            || str_contains($normalized, 'session')
            || str_contains($normalized, 'lock_token');
    }
}
