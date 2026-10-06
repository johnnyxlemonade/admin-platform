<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid;

use InvalidArgumentException;
use Lemonade\Admin\DataGrid\Action\DataGridRowActionDefinition;
use Lemonade\Admin\DataGrid\Cell\DataGridCell;
use Lemonade\Admin\DataGrid\Cell\DataGridCellSerializer;

/**
 * Drzi definici radku datove tabulky
 */
final readonly class DataGridRowDefinition
{
    /**
     * Vytvori radek s typed bunkami a dostupnymi akcemi
     *
     * @param array<string, DataGridCell> $cells
     * @param list<DataGridRowActionDefinition> $actions
     */
    public function __construct(
        private int $id,
        private array $cells,
        private array $actions = [],
    ) {
        foreach ($cells as $key => $cell) {
            if (!is_string($key) || !$cell instanceof DataGridCell) {
                throw new InvalidArgumentException('DataGrid cells must be a string-keyed map of DataGridCell instances.');
            }
        }
    }

    /**
     * Vrati radek ve formatu DataGrid JSON transportu
     *
     * @return array{id:int,cells:array<string, string|int|list<array{type:string,value:string,url?:string|null,download?:bool,modalUrl?:string|null,modalSize?:string|null,flag?:string,class?:string,translationKey?:string,secondary?:string,thumbnail?:array{url:string|null,alt:string,fallback:string|null,size:string,shape:string}}>>,actions:list<array{key:string,label:string,url:string,method:string,confirm:string|null,confirmTitle?:string,ariaLabel?:string,icon?:string,modalUrl?:string,modalSize?:string|null,kind?:string,placement?:string,risk?:string,refresh?:bool}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'cells' => array_map(static fn(DataGridCell $cell): string|int|array => DataGridCellSerializer::serialize($cell), $this->cells),
            'actions' => array_map(static fn(DataGridRowActionDefinition $action): array => $action->toArray(), $this->actions),
        ];
    }
}
