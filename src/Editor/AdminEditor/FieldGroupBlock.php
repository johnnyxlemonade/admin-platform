<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor;

use InvalidArgumentException;

/**
 * Zpracovava data potrebna pro administracni editor
 */
final readonly class FieldGroupBlock implements AdminEditorBlock
{
    /**
     * Nastavuje zavislosti a overuje vstupni hodnoty objektu
     * @param list<FieldColumn> $columns
     */
    public function __construct(private array $columns)
    {
        if ($columns === []) {
            throw new InvalidArgumentException('Field group must contain at least one FieldColumn.');
        }
        foreach ($columns as $column) {
            if (!$column instanceof FieldColumn) {
                throw new InvalidArgumentException('Field group must contain FieldColumn instances.');
            }
        }
    }

    /**
     * Zpracovava hodnotu columns v konfiguraci editoru
     * @return list<FieldColumn>
     */
    public function columns(): array
    {
        return $this->columns;
    }
}
