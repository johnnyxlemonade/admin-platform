<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Contract;

/**
 * Umoznuje editoru rozhodnout o pristupu k poli konkretniho zaznamu
 */
interface EditorFieldAccessPolicyInterface
{
    /**
     * Vrati rozhodnuti o pristupu k poli nebo jej prenecha dispeceru
     */
    public function fieldAccessDecision(int $id, string $field): ?bool;
}
