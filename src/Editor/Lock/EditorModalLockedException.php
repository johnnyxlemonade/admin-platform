<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\Lock;

use RuntimeException;

/**
 * Oznamuje chybu pri editormodallocked
 */
final class EditorModalLockedException extends RuntimeException
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     */
    public function __construct(
        private readonly ?EditorLockOwner $lockedBy,
    ) {
        parent::__construct('Editor record is locked.');
    }

    /**
     * Zpracovava hodnotu lockedby v konfiguraci editoru
     */
    public function lockedBy(): ?EditorLockOwner
    {
        return $this->lockedBy;
    }
}
