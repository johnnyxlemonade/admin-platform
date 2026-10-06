<?php

declare(strict_types=1);

namespace Lemonade\Admin\System\Languages\Editor;

/**
 * Poskytuje prazdne hodnoty create editoru bez vychoziho nebo aktivniho stavu
 */
final readonly class LanguageEditorDraft
{
    /**
     * Sklada hodnoty kompatibilni s modalnim create editorem
     *
     * @return array{
     *     id: null,
     *     code: string,
     *     name: string,
     *     flag_code: string,
     *     enabled: int,
     *     is_default: int,
     *     sort_order: int,
     * }
     */
    public function values(): array
    {
        return [
            'id' => null,
            'code' => '',
            'name' => '',
            'flag_code' => '',
            'enabled' => 0,
            'is_default' => 0,
            'sort_order' => 0,
        ];
    }
}
