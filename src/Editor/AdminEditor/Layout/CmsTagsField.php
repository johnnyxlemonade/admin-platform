<?php

declare(strict_types=1);

namespace Lemonade\Admin\Editor\AdminEditor\Layout;

use Lemonade\Admin\Editor\AdminEditor\Field\Field;

/**
 * Sklada pole stitku CMS se zachovanim vybranych hodnot v seznamu moznosti
 */
final class CmsTagsField
{
    /**
     * Brani vytvareni instanci stateless presentation helperu
     */
    private function __construct() {}

    /**
     * Vytvori sloupec pro vyber a vytvareni stitku
     *
     * @param list<string> $availableTags
     */
    public static function column(string $module, array $availableTags, mixed $selectedTags): FieldColumn
    {
        $options = [];
        if (is_array($selectedTags)) {
            foreach ($selectedTags as $tagName) {
                if (is_string($tagName)) {
                    $options[$tagName] = $tagName;
                }
            }
        }
        foreach ($availableTags as $tagName) {
            $options[$tagName] = $tagName;
        }

        return new FieldColumn(
            Field::select('tags', labelKey: $module . '.fields.tags')
                ->help('', $module . '.fields.tags_help')
                ->multiple()
                ->options($options)
                ->attributes(['data-lemonade-searchable' => true, 'data-lemonade-allow-create' => true]),
            md: 12,
        );
    }
}
