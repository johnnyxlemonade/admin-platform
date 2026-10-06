<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

use Lemonade\Admin\Auth\AuthenticatedUser;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class EditorLockIdentity
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    private function __construct(private string $key, private int $localUserId) {}

    /**
     * Zpracovava hodnotu local v konfiguraci editoru
     */
    public static function local(AuthenticatedUser $user): self
    {
        return new self('user:' . $user->id(), $user->id());
    }

    /**
     * Zpracovava hodnotu key v konfiguraci editoru
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Zpracovava hodnotu localuserid v konfiguraci editoru
     */
    public function localUserId(): int
    {
        return $this->localUserId;
    }
}
