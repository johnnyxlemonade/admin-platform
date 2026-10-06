<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Contract;

/**
 * Urcuje operace, ktere muze editor vyzadovat od editorlockbypasspolicy
 */
interface EditorLockBypassPolicyInterface
{
    /**
     * Rozhoduje stav canbypassforeignlock
     */
    public function canBypassForeignLock(int $id): bool;

    /**
     * Rozhoduje stav cansavewithoutlock
     * @param array<string, mixed> $payload
     */
    public function canSaveWithoutLock(int $id, array $payload): bool;
}
