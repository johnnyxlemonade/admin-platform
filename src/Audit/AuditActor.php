<?php

declare(strict_types=1);

namespace Lemonade\Admin\Audit;

use InvalidArgumentException;
use Lemonade\Admin\Identity\ActorIdentity;

/**
 * Nese identitu aktora auditni udalosti
 */
final readonly class AuditActor
{
    private function __construct(
        private AuditActorType $type,
        private string $key,
        private ?int $userId,
    ) {}

    public static function user(int $userId): self
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Audit user actor ID must be positive.');
        }

        $identity = ActorIdentity::local($userId);

        return new self(AuditActorType::User, $identity->key(), $identity->localUserId());
    }

    public static function external(string $key): self
    {
        return self::technical(AuditActorType::External, $key);
    }

    public static function system(string $key): self
    {
        return self::technical(AuditActorType::System, $key);
    }

    public static function cron(string $key): self
    {
        return self::technical(AuditActorType::Cron, $key);
    }

    public static function migration(string $key): self
    {
        return self::technical(AuditActorType::Migration, $key);
    }

    public function type(): AuditActorType
    {
        return $this->type;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    private static function technical(AuditActorType $type, string $key): self
    {
        $key = trim($key);
        if ($key === '' || preg_match('/^[a-z][a-z0-9._:-]{1,190}$/', $key) !== 1) {
            throw new InvalidArgumentException('Audit actor key is invalid.');
        }

        return new self($type, $key, null);
    }
}
