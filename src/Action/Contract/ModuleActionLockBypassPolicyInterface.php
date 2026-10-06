<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action\Contract;

/**
 * Umoznuje akci obejit zamek zaznamu vlastneny jinym uzivatelem
 */
interface ModuleActionLockBypassPolicyInterface
{
    /**
     * Urci zda muze akce obejit cizi zamek
     *
     * @param array<string, mixed> $payload
     */
    public function canBypassForeignLock(?int $id, array $payload): bool;
}
