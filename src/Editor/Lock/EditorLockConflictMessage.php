<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class EditorLockConflictMessage
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param array<string, string> $params
     */
    public function __construct(
        public string $key,
        public array $params,
    ) {}
}
