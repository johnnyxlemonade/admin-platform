<?php

declare(strict_types=1);

namespace Lemonade\Admin\Identity;

/**
 * Drzi canonical identitu aktera aplikace.
 */
final readonly class ActorIdentity
{
    private function __construct(
        private string $key,
        private int $localUserId,
    ) {}

    public static function local(int $userId): self
    {
        return new self('user:' . $userId, $userId);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function localUserId(): int
    {
        return $this->localUserId;
    }
}
