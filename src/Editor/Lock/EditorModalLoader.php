<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

use Lemonade\Admin\Editor\EditorDispatcher;
use Lemonade\Admin\Editor\EditorLoaded;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final class EditorModalLoader
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private readonly EditorDispatcher $editors,
        private readonly EditorLockManager $locks,
    ) {}

    /**
     * Nacita data editoru a overuje pristup k zaznamu
     */
    public function load(string $moduleCode, int $entityId): EditorLoaded
    {
        $editor = $this->editors->updateData($moduleCode, $entityId);
        $acquisition = $this->locks->acquire($moduleCode, (string) $entityId);

        if (!$acquisition->acquired) {
            throw new EditorModalLockedException($acquisition->lockedBy);
        }

        return $editor;
    }
}
