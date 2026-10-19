<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use InvalidArgumentException;

/**
 * Popisuje umisteni radku v hierarchickem DataGridu
 */
final readonly class DataGridTreeRowDefinition
{
    /**
     * Nastavuje volitelneho primeho rodice radku
     */
    public function __construct(private ?int $parentId)
    {
        if ($this->parentId !== null && $this->parentId < 1) {
            throw new InvalidArgumentException('DataGrid tree parent ID must be positive.');
        }
    }

    /**
     * Vrati identifikator primeho rodice nebo null pro koren
     */
    public function parentId(): ?int
    {
        return $this->parentId;
    }
}
