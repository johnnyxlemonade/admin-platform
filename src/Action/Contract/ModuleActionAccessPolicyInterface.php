<?php

declare(strict_types=1);

namespace Lemonade\Admin\Action\Contract;

/**
 * Umoznuje akci rozhodnout o pristupu k zaznamu
 */
interface ModuleActionAccessPolicyInterface
{
    /**
     * Vrati rozhodnuti o pristupu nebo prenecha kontrolu opravneni dispeceru
     */
    public function accessDecision(?int $id): ?bool;
}
