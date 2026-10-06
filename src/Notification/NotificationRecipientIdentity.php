<?php

declare(strict_types=1);

namespace Lemonade\Admin\Notification;

use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Popisuje data administracniho oznameni
 */
final readonly class NotificationRecipientIdentity
{
    /**
     * Nastavuje zavislosti potrebne pro zpracovani oznameni
     * @param list<string> $roleCodes
     */
    private function __construct(private string $key, private int $localUserId, private array $roleCodes) {}

    /**
     * Zpracovava hodnotu local pro oznameni
     * @param list<string> $roleCodes
     */
    public static function local(AuthenticatedUser $user, array $roleCodes): self
    {
        return new self('user:' . $user->id(), $user->id(), array_values(array_unique($roleCodes)));
    }

    /**
     * Zpracovava hodnotu key pro oznameni
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Zpracovava hodnotu localuserid pro oznameni
     */
    public function localUserId(): int
    {
        return $this->localUserId;
    }

    /**
     * Zpracovava hodnotu rolecodes pro oznameni
     * @return list<string>
     */
    public function roleCodes(): array
    {
        return $this->roleCodes;
    }
}
