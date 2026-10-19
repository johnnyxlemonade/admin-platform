<?php

declare(strict_types=1);

namespace Lemonade\Admin\DataGrid\Cell;

use LogicException;

/**
 * Preklada bunky tabulky do podoby pro zobrazeni
 */
final readonly class DataGridCellSerializer
{
    /**
     * Prevede typed bunku na hodnotu pro DataGrid JSON transport
     *
     * @return string|int|list<array{type:string,value:string,url?:string|null,download?:bool,modalUrl?:string|null,modalSize?:string|null,class?:string,translationKey?:string,secondary?:string,thumbnail?:array{url:string|null,alt:string,fallback:string|null,size:string,shape:string}}>
     */
    public static function serialize(DataGridCell $cell): string|int|array
    {
        if ($cell instanceof ScalarCell) {
            return $cell->value();
        }
        if ($cell instanceof LinkCell) {
            $presentation = ['type' => 'link', 'value' => $cell->value(), 'url' => $cell->url()];
            if ($cell->modalUrl() !== null) {
                $presentation['modalUrl'] = $cell->modalUrl();
                if ($cell->modalSize() !== null) {
                    $presentation['modalSize'] = $cell->modalSize();
                }
            }
            return [$presentation];
        }
        if ($cell instanceof CodeCell) {
            return [['type' => 'code', 'value' => $cell->value()]];
        }
        if ($cell instanceof StatusCell) {
            $presentation = ['type' => 'status', 'value' => $cell->value(), 'class' => $cell->variant()->value];
            $translationKey = $cell->translationKey();
            if ($translationKey !== null) {
                $presentation['translationKey'] = $translationKey;
            }

            return [$presentation];
        }
        if ($cell instanceof TranslationCell) {
            return [['type' => 'translation', 'translationKey' => $cell->translationKey(), 'value' => $cell->value()]];
        }
        if ($cell instanceof DateTimeCell) {
            return [['type' => 'datetime', 'value' => $cell->value()]];
        }
        if ($cell instanceof StackedCell) {
            $presentation = [
                'type' => 'stacked',
                'value' => $cell->value(),
                'secondary' => $cell->secondary(),
            ];
            if ($cell->url() !== null) {
                $presentation['url'] = $cell->url();
            }

            return [$presentation];
        }
        if ($cell instanceof ThumbnailCell) {
            $presentation = [
                'type' => 'thumbnail',
                'thumbnail' => $cell->thumbnail()->toArray(),
                'value' => $cell->value(),
                'url' => $cell->url(),
                'secondary' => $cell->secondary(),
            ];
            if ($cell->download()) {
                $presentation['download'] = true;
            }

            return [$presentation];
        }

        throw new LogicException(sprintf('Unsupported DataGrid cell %s.', $cell::class));
    }
}
