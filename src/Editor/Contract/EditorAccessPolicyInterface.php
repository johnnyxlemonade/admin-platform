<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Contract;

/**
 * Urcuje operace, ktere muze editor vyzadovat od editoraccesspolicy
 */
interface EditorAccessPolicyInterface
{
    /**
     * Zpracovava hodnotu loadaccessdecision v konfiguraci editoru
     */
    public function loadAccessDecision(int $id): ?bool;

    /**
     * Zpracovava hodnotu saveaccessdecision v konfiguraci editoru
     */
    public function saveAccessDecision(int $id): ?bool;
}
