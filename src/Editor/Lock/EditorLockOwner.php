<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class EditorLockOwner
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        public ?int $userId,
        public string $displayName,
    ) {}
}
