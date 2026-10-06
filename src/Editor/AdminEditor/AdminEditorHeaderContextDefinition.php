<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Popisuje prepinani kontextu v hlavicce administracniho editoru
 */
final readonly class AdminEditorHeaderContextDefinition
{
    /**
     * Nastavuje nazev prepinace a dostupne navigacni volby
     *
     * @param list<AdminEditorHeaderContextItem> $items
     */
    public function __construct(
        private string $label,
        private array $items,
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('Header context label must not be empty.');
        }
        if ($items === []) {
            throw new InvalidArgumentException('Header context requires at least one item.');
        }
        foreach ($items as $item) {
            if (!$item instanceof AdminEditorHeaderContextItem) {
                throw new InvalidArgumentException('Header context items must be AdminEditorHeaderContextItem instances.');
            }
        }
    }

    /**
     * Vrati nazev prepinaneho kontextu
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Vrati navigacni volby prepinace
     *
     * @return list<AdminEditorHeaderContextItem>
     */
    public function items(): array
    {
        return $this->items;
    }
}
